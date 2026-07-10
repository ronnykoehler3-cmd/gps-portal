<?php

namespace TKKundendienst\Component\Gpsportal\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use TKKundendienst\Component\Gpsportal\Administrator\Model\TodoModel;

class TodoController extends BaseController
{
    public function toggle()
    {
        $app = Factory::getApplication();

        $id = $app->input->getInt('id');

        $model = new TodoModel();

        $model->toggle($id);

        $app->redirect(
            'index.php?option=com_gpsportal&view=todo'
        );
    }
}
