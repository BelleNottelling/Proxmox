<?php

/**
 * Proxmox module for FOSSBilling
 *
 * @author   FOSSBilling (https://www.fossbilling.org) & Scitch (https://github.com/scitch)
 * @license  GNU General Public License version 3 (GPLv3)
 *
 * This software may contain code previously used in the BoxBilling project.
 * Copyright BoxBilling, Inc 2011-2021
 *
 * This source file is subject to the GNU General Public License version 3 (GPLv3) that is bundled
 * with this source code in the file LICENSE.
 */

namespace Box\Mod\Serviceproxmox\Api;

/**
 * Custom product management
 */
class Client extends \Api_Abstract
{
    /**
     * Universal method to call method from plugin
     * Pass any other params and they will be passed to plugin
     *
     * @param int $order_id - ID of the order
     *
     * @throws Box_Exception
     */
    public function __call($name, $arguments)
    {
        if (!isset($arguments[0])) {
            throw new \Box_Exception('API call is missing arguments', null, 7103);
        }

        $data = $arguments[0];

        if (!isset($data['order_id'])) {
            throw new \Box_Exception('Order ID is required');
        }
        $model = $this->getService()->getServiceproxmoxByOrderId($data['order_id']);

        return $this->getService()->customCall($model, $name, $data);
    }

    /**
     * Get server details
     * 
     * @param int $id - server id
     * @return array
     * 
     * @throws \Box_Exception 
     */
    public function server_get($data)
    {
        $required = array(
            'order_id'    => 'Order ID is missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $order = $this->di['db']->findOne(
            'client_order',
            "id=:id",
            array(':id' => $data['order_id'])
        );
        if (!$order) {
            throw new \Box_Exception('Order not found');
        }

        $service = $this->di['db']->findOne(
            'service_proxmox',
            "order_id=:id",
            array(':id' => $data['order_id'])
        );
        if (!$service) {
            throw new \Box_Exception('Proxmox service not found');
        }

        // Retrieve associated server
        $server  = $this->di['db']->findOne('service_proxmox_server', 'id=:id', array(':id' => $service['server_id']));

        $server_info = $this->getService()->vm_info($order, $service);

        $output = array(
            'hostname'  => $server->hostname,
            'username'  => 'root',
            'cli'       => $this->getService()->vm_cli($order, $service),
            'status'    => $server_info['status'],
        );
        return $output;
    }

    /**
     * Reboot vm
     */
    public function vm_manage($data)
    {
        $required = array(
            'order_id'    => 'Order ID is missing',
            'method'      => 'Method is missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $order = $this->di['db']->findOne(
            'client_order',
            "id=:id",
            array(':id' => $data['order_id'])
        );
        if (!$order) {
            throw new \Box_Exception('Order not found');
        }

        $service = $this->di['db']->findOne(
            'service_proxmox',
            "order_id=:id",
            array(':id' => $data['order_id'])
        );
        if (!$service) {
            throw new \Box_Exception('Proxmox service not found');
        }

        // Retrieve associated server
        $server  = $this->di['db']->findOne('service_proxmox_server', 'id=:id', array(':id' => $service['server_id']));

        switch ($data['method']) {
            case 'reboot':
                $this->getService()->vm_reboot($order, $service);
                break;
            case 'start':
                $this->getService()->vm_start($order, $service);
                break;
            case 'shutdown':
                $this->getService()->vm_shutdown($order, $service);
            default:
                return false;
        }

        return true;
    }
}
