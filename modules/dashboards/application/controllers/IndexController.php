<?php

namespace Icinga\Module\Dashboards\Controllers;

use Icinga\Authentication\Auth;
use Icinga\Module\Dashboards\Common\Database;
use Icinga\Module\Dashboards\Web\Controller;
use Icinga\Module\Dashboards\Web\Widget\Tabextension\DashboardAction;
use Icinga\Module\Dashboards\Web\Widget\DashboardWidget;
use Icinga\Web\Notification;
use Icinga\Web\Url;
use ipl\Sql\Select;

class IndexController extends Controller
{
    use Database;

    public function indexAction()
    {
        try {
            $this->createTabsAndAutoActivateDashboard();
        } catch (\Exception $e) {
            $this->tabs->extend(new DashboardAction())->disableLegacyExtensions();

            Notification::error('No dashboard or dashlet found');
        }

        $select = (new Select())
            ->columns('*')
            ->from('dashlet')
            ->where([
                'dashlet.dashboard_id = ?' => $this->tabs->getActiveName(),
                'dashlet.type = "system" OR dashlet.owner = ?' => Auth::getInstance()->getUser()->getUsername()
            ])
            ->orderBy('dashlet.id');

        $dashlets = $this->getDb()->select($select);

        $this->content = new DashboardWidget($dashlets);
    }

    /**
     * create Tabs and
     * activate the first dashboard from the database when the url $params doesn't given
     *
     * @return int
     *
     * @throws \Icinga\Exception\NotFoundError      If the database doesn't have a dashboard
     */
    protected function createTabsAndAutoActivateDashboard()
    {
        $tabs = $this->getTabs();

        $select = (new Select())
            ->columns('*')
            ->from('dashboard')
            ->where([
                'dashboard.type = "system" OR dashboard.owner = ?' => Auth::getInstance()->getUser()->getUsername()
            ])
            ->orderBy('dashboard.id');

        $dashboards = $this->getDb()->select($select);

        foreach ($dashboards as $dashboard) {
            $tabs->add($dashboard->id, [
                'label' => $dashboard->name,
                'url' => Url::fromPath('dashboards', [
                    'dashboard' => $dashboard->id
                ])
            ])->extend(new DashboardAction())->disableLegacyExtensions();

            $ids[] = $dashboard->id;
        }

        $id = $this->params->get('dashboard') ?: array_shift($ids);

        $tabs->activate($id);

        return $id;
    }
}
