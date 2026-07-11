#!/usr/bin/env python3

from __future__ import annotations

import json
import logging
import math
import random
import signal
import sys
import threading
import time
import urllib.error
import urllib.parse
import urllib.request
from dataclasses import dataclass, field
from datetime import datetime
from pathlib import Path
from typing import Any

INSTALL_DIR = Path("/opt/tk-gps-simulator")
CONFIG_PATH = INSTALL_DIR / "config.json"
STATE_PATH = INSTALL_DIR / "state.json"

STOP_EVENT = threading.Event()
STATE_LOCK = threading.Lock()


def load_json(path: Path) -> dict[str, Any]:
    with path.open("r", encoding="utf-8") as handle:
        return json.load(handle)


CONFIG = load_json(CONFIG_PATH)


def now_timestamp() -> int:
    return int(time.time())


def haversine_meters(a: tuple[float, float], b: tuple[float, float]) -> float:
    lon1, lat1 = a
    lon2, lat2 = b

    radius = 6_371_000.0

    phi1 = math.radians(lat1)
    phi2 = math.radians(lat2)
    delta_phi = math.radians(lat2 - lat1)
    delta_lambda = math.radians(lon2 - lon1)

    value = (
        math.sin(delta_phi / 2.0) ** 2
        + math.cos(phi1)
        * math.cos(phi2)
        * math.sin(delta_lambda / 2.0) ** 2
    )

    return radius * 2.0 * math.atan2(math.sqrt(value), math.sqrt(1.0 - value))


def bearing_degrees(a: tuple[float, float], b: tuple[float, float]) -> float:
    lon1, lat1 = map(math.radians, a)
    lon2, lat2 = map(math.radians, b)

    delta_lon = lon2 - lon1

    x_value = math.sin(delta_lon) * math.cos(lat2)
    y_value = (
        math.cos(lat1) * math.sin(lat2)
        - math.sin(lat1) * math.cos(lat2) * math.cos(delta_lon)
    )

    return (math.degrees(math.atan2(x_value, y_value)) + 360.0) % 360.0


def interpolate(
    start: tuple[float, float],
    end: tuple[float, float],
    fraction: float,
) -> tuple[float, float]:
    lon1, lat1 = start
    lon2, lat2 = end

    return (
        lon1 + (lon2 - lon1) * fraction,
        lat1 + (lat2 - lat1) * fraction,
    )


def http_json(url: str, timeout: int) -> dict[str, Any]:
    request = urllib.request.Request(
        url,
        headers={
            "User-Agent": "TK-GPS-Simulator/1.0",
            "Accept": "application/json",
        },
    )

    with urllib.request.urlopen(request, timeout=timeout) as response:
        return json.loads(response.read().decode("utf-8"))


def request_route(
    start: tuple[float, float],
    destination: tuple[float, float],
) -> tuple[list[tuple[float, float]], float, float]:
    coordinates = (
        f"{start[0]:.6f},{start[1]:.6f};"
        f"{destination[0]:.6f},{destination[1]:.6f}"
    )

    base_url = str(CONFIG["osrm_url"]).rstrip("/")

    url = (
        f"{base_url}/route/v1/driving/{coordinates}"
        "?overview=full"
        "&geometries=geojson"
        "&steps=false"
        "&alternatives=false"
    )

    data = http_json(url, int(CONFIG["route_timeout_seconds"]))

    if data.get("code") != "Ok" or not data.get("routes"):
        raise RuntimeError(f"OSRM lieferte keine Route: {data.get('code')}")

    route = data["routes"][0]
    geometry = route["geometry"]["coordinates"]

    points = [(float(point[0]), float(point[1])) for point in geometry]

    if len(points) < 2:
        raise RuntimeError("OSRM-Route enthält zu wenige Punkte")

    return points, float(route["distance"]), float(route["duration"])


def send_position(
    unique_id: str,
    coordinate: tuple[float, float],
    speed_kmh: float,
    bearing: float,
    valid: bool = True,
) -> None:
    lon, lat = coordinate

    # Das OsmAnd-Protokoll erwartet speed üblicherweise in Knoten.
    speed_knots = max(0.0, speed_kmh / 1.852)

    parameters = {
        "id": unique_id,
        "timestamp": str(now_timestamp()),
        "lat": f"{lat:.7f}",
        "lon": f"{lon:.7f}",
        "speed": f"{speed_knots:.2f}",
        "bearing": f"{bearing:.1f}",
        "altitude": "70",
        "accuracy": "5",
        "batt": str(random.randint(70, 100)),
        "valid": "true" if valid else "false",
    }

    url = (
        str(CONFIG["traccar_osmand_url"])
        + "?"
        + urllib.parse.urlencode(parameters)
    )

    request = urllib.request.Request(
        url,
        headers={
            "User-Agent": "TK-GPS-Simulator/1.0",
            "Connection": "close",
        },
    )

    try:
        with urllib.request.urlopen(request, timeout=10) as response:
            response.read()
    except urllib.error.URLError as error:
        raise RuntimeError(f"Traccar nicht erreichbar: {error}") from error


def save_state(vehicle_id: str, values: dict[str, Any]) -> None:
    with STATE_LOCK:
        if STATE_PATH.exists():
            try:
                state = load_json(STATE_PATH)
            except (OSError, json.JSONDecodeError):
                state = {}
        else:
            state = {}

        state[vehicle_id] = values

        temporary = STATE_PATH.with_suffix(".tmp")

        with temporary.open("w", encoding="utf-8") as handle:
            json.dump(state, handle, indent=2, ensure_ascii=False)

        temporary.replace(STATE_PATH)


def is_work_time() -> bool:
    current = datetime.now()

    if current.weekday() not in CONFIG["working_weekdays"]:
        return False

    start_minutes = (
        int(CONFIG["workday_start_hour"]) * 60
        + int(CONFIG["workday_start_minute"])
    )

    end_minutes = (
        int(CONFIG["workday_end_hour"]) * 60
        + int(CONFIG["workday_end_minute"])
    )

    current_minutes = current.hour * 60 + current.minute

    return start_minutes <= current_minutes < end_minutes


def choose_stop_seconds() -> int:
    if random.random() < float(CONFIG["long_stop_probability"]):
        return random.randint(180, 240) * 60

    minimum = int(CONFIG["minimum_stop_minutes"])
    maximum = min(90, int(CONFIG["maximum_stop_minutes"]))

    return random.randint(minimum, maximum) * 60


def realistic_speed_kmh(
    route_distance: float,
    route_duration: float,
    progress: float,
) -> float:
    if route_duration <= 0:
        average = 35.0
    else:
        average = route_distance / route_duration * 3.6

    average = max(18.0, min(105.0, average))

    # Langsamer beim Anfahren und vor dem Ziel.
    if progress < 0.04:
        factor = max(0.25, progress / 0.04)
    elif progress > 0.94:
        factor = max(0.20, (1.0 - progress) / 0.06)
    else:
        factor = 1.0

    traffic_factor = random.uniform(0.82, 1.12)

    return max(5.0, min(125.0, average * factor * traffic_factor))


@dataclass
class VehicleSimulator:
    name: str
    unique_id: str
    region: str
    home: tuple[float, float]
    waypoints: list[tuple[float, float]]
    current: tuple[float, float] = field(init=False)
    last_bearing: float = field(default=0.0)

    def __post_init__(self) -> None:
        self.current = self.home

    def send_parked(self) -> None:
        send_position(
            self.unique_id,
            self.current,
            speed_kmh=0.0,
            bearing=self.last_bearing,
        )

        save_state(
            self.unique_id,
            {
                "name": self.name,
                "region": self.region,
                "status": "parked",
                "longitude": self.current[0],
                "latitude": self.current[1],
                "updated": datetime.now().isoformat(),
            },
        )

    def wait_parked(self, seconds: int) -> None:
        end_time = time.monotonic() + seconds
        parked_interval = int(CONFIG["parked_interval_seconds"])

        while not STOP_EVENT.is_set() and time.monotonic() < end_time:
            self.send_parked()
            remaining = end_time - time.monotonic()
            STOP_EVENT.wait(min(parked_interval, max(1.0, remaining)))

    def drive_route(
        self,
        points: list[tuple[float, float]],
        route_distance: float,
        route_duration: float,
    ) -> None:
        segment_lengths = [
            haversine_meters(points[index], points[index + 1])
            for index in range(len(points) - 1)
        ]

        total_geometry_distance = sum(segment_lengths)

        if total_geometry_distance <= 0:
            raise RuntimeError("Route hat keine nutzbare Länge")

        travelled = 0.0
        segment_index = 0
        segment_position = 0.0

        while (
            not STOP_EVENT.is_set()
            and travelled < total_geometry_distance
        ):
            interval = random.uniform(
                float(CONFIG["minimum_interval_seconds"]),
                float(CONFIG["maximum_interval_seconds"]),
            )

            progress = travelled / total_geometry_distance

            speed_kmh = realistic_speed_kmh(
                route_distance,
                route_duration,
                progress,
            )

            # Gelegentlich kurze realistische Verkehrsunterbrechung.
            if random.random() < 0.008:
                pause_seconds = random.randint(8, 45)
                logging.info(
                    "%s: kurzer Verkehrsstopp für %s Sekunden",
                    self.name,
                    pause_seconds,
                )
                self.wait_parked(pause_seconds)
                continue

            step_distance = speed_kmh / 3.6 * interval
            remaining_step = step_distance

            while (
                remaining_step > 0
                and segment_index < len(segment_lengths)
            ):
                segment_length = segment_lengths[segment_index]

                if segment_length <= 0:
                    segment_index += 1
                    segment_position = 0.0
                    continue

                available = segment_length - segment_position

                if remaining_step < available:
                    segment_position += remaining_step
                    remaining_step = 0.0
                else:
                    remaining_step -= available
                    segment_index += 1
                    segment_position = 0.0

            if segment_index >= len(segment_lengths):
                self.current = points[-1]
                travelled = total_geometry_distance
            else:
                fraction = segment_position / segment_lengths[segment_index]

                self.current = interpolate(
                    points[segment_index],
                    points[segment_index + 1],
                    fraction,
                )

                travelled = (
                    sum(segment_lengths[:segment_index])
                    + segment_position
                )

                self.last_bearing = bearing_degrees(
                    points[segment_index],
                    points[segment_index + 1],
                )

            send_position(
                self.unique_id,
                self.current,
                speed_kmh=speed_kmh,
                bearing=self.last_bearing,
            )

            save_state(
                self.unique_id,
                {
                    "name": self.name,
                    "region": self.region,
                    "status": "driving",
                    "speed_kmh": round(speed_kmh, 1),
                    "longitude": self.current[0],
                    "latitude": self.current[1],
                    "progress_percent": round(
                        travelled / total_geometry_distance * 100.0,
                        1,
                    ),
                    "updated": datetime.now().isoformat(),
                },
            )

            STOP_EVENT.wait(interval)

        self.current = points[-1]

        send_position(
            self.unique_id,
            self.current,
            speed_kmh=0.0,
            bearing=self.last_bearing,
        )

    def select_destination(self) -> tuple[float, float]:
        candidates = [
            point
            for point in self.waypoints
            if haversine_meters(self.current, point) > 1500
        ]

        if not candidates:
            candidates = self.waypoints

        return random.choice(candidates)

    def run(self) -> None:
        startup_delay = random.randint(0, 60)
        STOP_EVENT.wait(startup_delay)

        logging.info(
            "%s gestartet, Gebiet %s",
            self.name,
            self.region,
        )

        while not STOP_EVENT.is_set():
            try:
                if not is_work_time():
                    self.wait_parked(int(CONFIG["parked_interval_seconds"]))
                    continue

                destination = self.select_destination()

                logging.info(
                    "%s: berechne Route %.6f,%.6f -> %.6f,%.6f",
                    self.name,
                    self.current[1],
                    self.current[0],
                    destination[1],
                    destination[0],
                )

                points, distance, duration = request_route(
                    self.current,
                    destination,
                )

                logging.info(
                    "%s: Fahrt %.1f km, OSRM-Dauer %.0f Minuten",
                    self.name,
                    distance / 1000.0,
                    duration / 60.0,
                )

                self.drive_route(points, distance, duration)

                stop_seconds = choose_stop_seconds()

                logging.info(
                    "%s: Kundenstopp für %.0f Minuten",
                    self.name,
                    stop_seconds / 60.0,
                )

                self.wait_parked(stop_seconds)

            except Exception:
                logging.exception("%s: Simulationsfehler", self.name)
                STOP_EVENT.wait(30)


def handle_signal(signum: int, frame: Any) -> None:
    del signum, frame
    STOP_EVENT.set()


def main() -> int:
    logging.basicConfig(
        level=logging.INFO,
        format="%(asctime)s %(levelname)s %(threadName)s: %(message)s",
    )

    signal.signal(signal.SIGTERM, handle_signal)
    signal.signal(signal.SIGINT, handle_signal)

    threads: list[threading.Thread] = []

    for vehicle_data in CONFIG["vehicles"]:
        simulator = VehicleSimulator(
            name=str(vehicle_data["name"]),
            unique_id=str(vehicle_data["unique_id"]),
            region=str(vehicle_data["region"]),
            home=tuple(vehicle_data["home"]),
            waypoints=[
                tuple(point)
                for point in vehicle_data["waypoints"]
            ],
        )

        thread = threading.Thread(
            target=simulator.run,
            name=simulator.unique_id,
            daemon=True,
        )

        thread.start()
        threads.append(thread)

    while not STOP_EVENT.is_set():
        STOP_EVENT.wait(1)

    for thread in threads:
        thread.join(timeout=10)

    return 0


if __name__ == "__main__":
    sys.exit(main())
