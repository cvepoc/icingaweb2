<?php
// @codingStandardsIgnoreStart
// {{{ICINGA_LICENSE_HEADER}}}
/**
 * This file is part of Icinga 2 Web.
 *
 * Icinga 2 Web - Head for multiple monitoring backends.
 * Copyright (C) 2013 Icinga Development Team
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 *
 * @copyright 2013 Icinga Development Team <info@icinga.org>
 * @license   http://www.gnu.org/licenses/gpl-2.0.txt GPL, version 2
 * @author    Icinga Development Team <info@icinga.org>
 */
// {{{ICINGA_LICENSE_HEADER}}}
use Icinga\Web\Controller\ModuleActionController;
use Icinga\Web\Hook;
use Icinga\File\Csv;
use Monitoring\Backend;
use Icinga\Application\Benchmark;
use Icinga\Web\Widget\Tabextension\OutputFormat;
use Icinga\Web\Widget\Tabextension\DashboardAction;
use Icinga\Web\Widget\Tabextension\BasketAction;

class Monitoring_ListController extends ModuleActionController
{
    /**
     * The backend used for this controller
     *
     * @var \Icinga\Backend
     */
    protected $backend;

    /**
     * Set to a string containing the compact layout name to use when
     * 'compact' is set as the layout parameter, otherwise null
     *
     * @var string|null
     */
    private $compactView = null;

    /**
     * Retrieve backend and hooks for this controller
     *
     * @see ActionController::init
     */
    public function init()
    {
        $this->backend = Backend::getInstance($this->_getParam('backend'));
        $this->view->grapher = Hook::get('grapher');
        $this->createTabs();
    }

    /**
     * Display host list
     */
    public function hostsAction()
    {
        Benchmark::measure("hostsAction::query()");
        $this->compactView = "hosts-compact";
        $this->view->hosts = $this->query(
            'status',
            array(
                'host_icon_image',
                'host_name',
                'host_state',
                'host_address',
                'host_acknowledged',
                'host_output',
                'host_long_output',
                'host_in_downtime',
                'host_is_flapping',
                'host_state_type',
                'host_handled',
                'host_last_check',
                'host_last_state_change',
                'host_notifications_enabled',
                'host_unhandled_service_count',
                'host_action_url',
                'host_notes_url',
                'host_last_comment'
            )
        );
    }

    /**
     * Display service list
     */
    public function servicesAction()
    {
        $state_type = $this->_getParam('_statetype', 'soft');
        if ($state_type = 'soft') {
            $state_column = 'service_state';
            $state_change_column = 'service_last_state_change';
        } else {
            $state_column = 'service_hard_state';
            $state_change_column = 'service_last_hard_state_change';
        }
        $this->compactView = "services-compact";


        $this->view->services = $this->query('status', array(
            'host_name',
            'host_state',
            'host_state_type',
            'host_last_state_change',
            'host_address',
            'host_handled',
            'service_description',
            'service_display_name',
            'service_state' => $state_column,
            'service_in_downtime',
            'service_acknowledged',
            'service_handled',
            'service_output',
            'service_last_state_change' => $state_change_column,
            'service_icon_image',
            'service_long_output',
            'service_is_flapping',
            'service_state_type',
            'service_handled',
            'service_severity',
            'service_last_check',
            'service_notifications_enabled',
            'service_action_url',
            'service_notes_url',
            'service_last_comment'
        ));
        $this->inheritCurrentSortColumn();
    }

    /**
     * Display hostgroup list
     *
     * @TODO Implement hostgroup overview (feature #4184)
     */
    public function hostgroupsAction()
    {
        $this->view->hostgroups = $this->backend->select()
            ->from('hostgroup', array(
            'hostgroup_name',
            'hostgroup_alias',
        ))->applyRequest($this->_request);
    }

    /**
     * Display servicegroup list
     *
     * @TODO Implement servicegroup overview (feature #4185)
     */
    public function servicegroupsAction()
    {
        $this->view->servicegroups = $this->backend->select()
            ->from('servicegroup', array(
            'servicegroup_name',
            'servicegroup_alias',
        ))->applyRequest($this->_request);
    }

    /**
     * Display contactgroups overview
     *
     *
     */
    public function contactgroupsAction()
    {
        $this->view->contactgroups = $this->backend->select()
            ->from('contactgroup', array(
            'contactgroup_name',
            'contactgroup_alias',
        ))->applyRequest($this->_request);
    }

    /**
     * Fetch the current downtimes and put them into the view
     * property 'downtimes'
     */
    public function downtimesAction()
    {
         $query = $this->backend->select()
            ->from('downtime',array(
                'host_name',
                'object_type',
                'service_description',
                'downtime_entry_time',
                'downtime_internal_downtime_id',
                'downtime_author_name',
                'downtime_comment_data',
                'downtime_duration',
                'downtime_scheduled_start_time',
                'downtime_scheduled_end_time',
                'downtime_is_fixed',
                'downtime_is_in_effect',
                'downtime_triggered_by_id',
                'downtime_trigger_time'
        ));
        if (!$this->_getParam('sort')) {
            $query->order('downtime_is_in_effect');
        }
        $this->view->downtimes = $query->applyRequest($this->_request);
        $this->inheritCurrentSortColumn();
    }

    /**
     * Create a query for the given view
     *
     * @param string $view              An string identifying view to query
     * @param array $columns            An array with the column names to display
     *
     * @return \Icinga\Data\Db\Query
     */
    protected function query($view, $columns)
    {
        $extra = preg_split(
            '~,~',
            $this->_getParam('extracolumns', ''),
            -1,
            PREG_SPLIT_NO_EMPTY
        );

        $this->view->extraColumns = $extra;
        $query = $this->backend->select()
            ->from($view, array_merge($columns, $extra))
            ->applyRequest($this->_request);
        $this->handleFormatRequest($query);
        return $query;
    }

    /**
     * Handle the 'format' and 'view' parameter
     *
     * @param \Icinga\Data\Db\Query $query      The current query
     */
    protected function handleFormatRequest($query)
    {
        if ($this->compactView !== null && ($this->_getParam('view', false) === 'compact')) {
            $this->_helper->viewRenderer($this->compactView);
        }

        if ($this->_getParam('format') === 'sql') {
            echo '<pre>'
                . htmlspecialchars(wordwrap($query->getQuery()->dump()))
                . '</pre>';
            exit;
        }
        if ($this->_getParam('format') === 'json'
            || $this->_request->getHeader('Accept') === 'application/json')
        {
            header('Content-type: application/json');
            echo json_encode($query->fetchAll());
            exit;
        }
        if ($this->_getParam('format') === 'csv'
            || $this->_request->getHeader('Accept') === 'text/csv') {
            Csv::fromQuery($query)->dump();
            exit;
        }
    }

    /**
     * Return all tabs for this controller
     *
     * @return Tabs
     */
    protected function createTabs()
    {

        $tabs = $this->getTabs();
        $tabs->extend(new OutputFormat())
            ->extend(new DashboardAction())
            ->extend(new BasketAction());

        $tabs->add('services', array(
            'title'     => 'All services',
            'icon'      => 'img/classic/service.png',
            'url'       => 'monitoring/list/services',
        ));
        $tabs->add('hosts', array(
            'title'     => 'All hosts',
            'icon'      => 'img/classic/server.png',
            'url'       => 'monitoring/list/hosts',
        ));
        $tabs->add('downtimes', array(
            'title'     => 'Downtimes',
            'usePost'   => true,
            'icon'      => 'img/classic/downtime.gif',
            'url'       => 'monitoring/list/downtimes',
        ));


/*
        $tabs->add('hostgroups', array(
            'title'     => 'Hostgroups',
            'icon'      => 'img/classic/servers-network.png',
            'url'       => 'monitoring/list/hostgroups',
        ));
        $tabs->add('servicegroups', array(
            'title'     => 'Servicegroups',
            'icon'      => 'img/classic/servers-network.png',
            'url'       => 'monitoring/list/servicegroups',
        ));
        $tabs->add('contacts', array(
            'title'     => 'Contacts',
            'icon'      => 'img/classic/servers-network.png',
            'url'       => 'monitoring/list/contacts',
        ));
        $tabs->add('contactgroups', array(
            'title'     => 'Contactgroups',
            'icon'      => 'img/classic/servers-network.png',
            'url'       => 'monitoring/list/contactgroups',
        ));
*/
    }


    /**
     * Let the current response inherit the used sort column by applying it to the
     * view property 'sort'
     */
    private function inheritCurrentSortColumn()
    {
        if ($this->_getParam('sort')) {
            $this->view->sort = $this->_getParam('sort');
        }
    }
}
// @codingStandardsIgnoreEnd
