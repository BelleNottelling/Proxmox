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

/* Manage the Proxmox Hosting Service */

class Admin extends \Api_Abstract
{
    /* ################################################################################################### */
    /* ##########################################   Lists   ############################################## */
    /* ################################################################################################### */

    /**
     * Get list of servers
     * 
     * @return array
     */
    public function server_get_list($data)
    {
        $servers = $this->di['db']->find('service_proxmox_server');
        $servers_grouped = array();

        foreach ($servers as $server) {
            // find all vms on this server and count cpu cores and ram to return in servers_grouped
            $vms = $this->di['db']->find('service_proxmox', 'server_id=:id', array(':id' => $server->id));
            $server_cpu_cores = 0;
            $server_ram = 0;
            foreach ($vms as $vm) {
                $server_cpu_cores += $vm->cpu_cores;
                $server_ram += $vm->ram;
            }

            $servers_grouped[$server['group']]['group'] = $server->group;
            $servers_grouped[$server['group']]['servers'][$server['id']] = array(
                'id'                    => $server->id,
                'name'                  => $server->name,
                'group'                 => $server->group,
                'ipv4'                  => $server->ipv4,
                'hostname'              => $server->hostname,
                'access'                => $this->getService()->find_access($server),
                'cpu_cores'             => $server->cpu_cores,
                'cpu_cores_allocated'   => $server_cpu_cores,
                'ram_allocated'         => $server_ram,
                'ram'                   => round($server->ram / 1024 / 1024, 0, PHP_ROUND_HALF_DOWN),
                'active'                => $server->active,
            );
        }
        return $servers_grouped;
    }

    /**
     * Get list of storage
     * 
     * @return array
     */
    public function storage_get_list($data)
    {
        $storages = $this->di['db']->find('service_proxmox_storage');
        $storages_grouped = array();
        foreach ($storages as $storage) {
            $server = $this->di['db']->getExistingModelById('service_proxmox_server', $storage->server_id, 'Server not found');
            $storages_grouped[$storage['type']]['group'] = $storage->type;
            $storages_grouped[$storage['type']]['storages'][$storage['id']] = array(
                'id'            => $storage->id,
                'servername'    => $server->name,
                'storageclass'  => $storage->storageclass,
                'name'          => $storage->storage,
                'content'       => $storage->content,
                'type'          => $storage->type,
                'active'        => $storage->active,
                'size'          => $storage->size,
                'used'          => $storage->used,
                'free'          => $storage->free,
                // add percentage used
                'percent_used'  => round($storage->used / $storage->size * 100, 2),
            );
            //echo "<script>console.log('Debug Objects: " . json_encode($storages_grouped). "' );</script>";
        }
        return $storages_grouped;
    }

    /**
     * Get list of storageclasses
     * 
     * @return array
     */

    public function storageclass_get_list($data)
    {
        $storageclasses = $this->di['db']->find('service_proxmox_storageclass');
        return $storageclasses;
    }

    /** *
     * Get list of Active Services
     * 
     * @return array
     */
    public function service_proxmox_get_list($data)
    {
        $services = $this->di['db']->find('service_proxmox');
        return $services;
    }

    /** 
     *	Get list of server groups
     *
     *   @return array
     */
    public function server_groups()
    {
        $sql = "SELECT DISTINCT `group` FROM `service_proxmox_server` WHERE `active` = 1";
        $groups = $this->di['db']->getAll($sql);
        return $groups;
    }

    /* ################################################################################################### */
    /* ##########################################  Servers  ############################################## */
    /* ################################################################################################### */

    /**
     * Create new hosting server 
     * 
     * @param string $name - server name
     * @param string $ip - server ip
     * @optional string $hostname - server hostname
     * @optional string $username - server API login username
     * @optional string $password - server API login password
     * @optional bool $active - flag to enable/disable server
     * 
     * @return int - server id 
     * @throws \Box_Exception 
     */
    public function server_create($data)
    {
        // enable api token & secret
        $required = array(
            'name'              => 'Server name is missing',
            'ipv4'              => 'Server ipv4 is missing',
            'hostname'          => 'Server hostname is missing',
            'root_user'         => 'Root user is missing',
            'root_password'     => 'Root password is missing',
            'realm'             => 'Proxmox user realm is missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $server                     = $this->di['db']->dispense('service_proxmox_server');
        $server->name               = $data['name'];
        $server->group              = $data['group'];
        $server->ipv4               = $data['ipv4'];
        $server->ipv6               = $data['ipv6'];
        $server->hostname           = $data['hostname'];
        $server->realm              = $data['realm'];
        $server->slots              = $data['slots'];
        $server->root_user          = $data['root_user'];
        $server->root_password      = $data['root_password'];
        $server->config             = $data['config'];
        $server->active             = $data['active'];
        $server->created_at         = date('Y-m-d H:i:s');
        $server->updated_at         = date('Y-m-d H:i:s');

        //@TODO: Here comes the logic that needs to happen when a server is created

        $this->di['db']->store($server);


        $this->di['logger']->info('Create Proxmox server %s', $server->id);

        return true;
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
        // Retrieve associated server
        $server  = $this->di['db']->findOne('service_proxmox_server', 'id=:id', array(':id' => $data['server_id']));

        //TODO: Update settings
        $output = array(
            'id'                => $server->id,
            'name'              => $server->name,
            'group'             => $server->group,
            'ipv4'              => $server->ipv4,
            'ipv6'              => $server->ipv6,
            'hostname'          => $server->hostname,
            'realm'             => $server->realm,
            'tokenname'         => $server->tokenname,
            'tokenvalue'        => str_repeat("*", 26),
            //'access'          => $this->getService()->find_access($server),
            'slots'             => $server->slots,
            'root_user'         => $server->root_user,
            'root_password'     => $server->root_password,
            'admin_password'    => $server->admin_password,
            'active'            => $server->active,
        );
        return $output;
    }

    /**
     * Update server configuration
     * 
     * @param int $id - server id
     * 
     * @optional string $hostname - server hostname
     * @optional string $username - server API login username
     * @optional string $password - server API login password
     * @optional bool $active - flag to enable/disable server
     * 
     * @return boolean
     * @throws \Box_Exception 
     */
    public function server_update($data)
    {
        $required = array(
            'name'    => 'Server name is missing',
            'root_user'      => 'Root user is missing',
            'realm'      => 'Proxmox user realm is missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $server  = $this->di['db']->findOne('service_proxmox_server', 'id=:id', array(':id' => $data['server_id']));

        $server->name             = $data['name'];
        $server->group            = $data['group'];
        $server->ipv4             = $data['ipv4'];
        $server->ipv6             = $data['ipv6'];
        $server->hostname         = $data['hostname'];
        $server->realm            = $data['realm'];
        $server->slots            = $data['slots'];
        $server->cpu_cores        = $data['cpu_cores'];
        $server->ram              = $data['ram'];
        $server->root_user        = $data['root_user'];
        $server->root_password   = $data['root_password'];
        $server->tokenname        = $data['tokenname'];
        $server->config           = $data['config'];
        $server->active           = $data['active'];
        $server->created_at       = date('Y-m-d H:i:s');
        $server->updated_at       = date('Y-m-d H:i:s');
        $this->di['db']->store($server);
        $this->di['logger']->info('Update Proxmox server %s', $server->id);

        return true;
    }

    /**
     * Delete server
     * 
     * @param int $id - server id
     * @return boolean
     * @throws \Box_Exception 
     */
    public function server_delete($data)
    {
        $required = array(
            'id'    => 'Server id is missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);
        // check if there are vms provisioned on this server
        $vms = $this->di['db']->findAll('service_proxmox', 'server_id = :server_id', array(':server_id' => $data['id']));
        // if there are vms provisioned on this server, throw an exception
        if (!empty($vms)) {
            throw new \Box_Exception('VMs are still provisioned on this server');
        }
        // delete server
        $server = $this->di['db']->getExistingModelById('service_proxmox_server', $data['id'], 'Server not found');
        $this->di['db']->trash($server);
    }

    /**
     *   Get server details from order id
     *   This is used to manage the service from the order. 
     *   TODO: Remove this function and use server_get instead 
     *   @param int $order_id
     *   @return array
     */

    public function server_get_from_order($data)
    {
        $required = array(
            'order_id'    => 'Order id is missing',
        );

        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $service = $this->di['db']->findOne(
            'service_proxmox',
            "order_id=:id",
            array(':id' => $data['order_id'])
        );

        if (!$service) {
            return null;
        }

        $data = array('server_id' => $service['server_id']);
        $output = $this->server_get($data);
        return $output;
    }


    /**
     * Receive Hardware Data from proxmox server
     *
     * @param int $server_id
     * @return array
     */
    public function get_hardware_data($server_id)
    {
        $server = $this->di['db']->getExistingModelById('service_proxmox_server', $server_id, 'Server not found');
        $service = $this->getService();
        $hardware_data = $service->getHardwareData($server);
        $server->cpu_cores = $hardware_data['cpuinfo']['cores'];
        $server->ram = $hardware_data['memory']['total'];

        $serverstorage = $service->getStorageData($server);

        foreach ($serverstorage as $key => $value) {
            //echo "<script> console.log('serverstorage: " . json_encode($value['storage']) . "' ); </script>";
            $sql = "SELECT * FROM `service_proxmox_storage` WHERE server_id = " . $server_id . " AND storage = '" . $value['storage'] . "'";
            $storage = $this->di['db']->getAll($sql);

            // if the storage exists, update it, otherwise create it
            if (!empty($storage)) {
                $storage = $this->di['db']->findOne('service_proxmox_storage', 'server_id=:server_id AND storage=:storage', array(':server_id' => $server_id, ':storage' => $value['storage']));
            } else {
                $storage = $this->di['db']->dispense('service_proxmox_storage');
            }

            $storage->server_id = $server_id;
            $storage->storage = $value['storage'];
            $storage->type = $value['type'];
            $storage->content = $value['content'];

            $storage->used = $value['used'] / 1000 / 1000 / 1000;
            $storage->size = $value['total'] / 1000 / 1000 / 1000;
            $storage->free = $value['avail'] / 1000 / 1000 / 1000;

            $storage->active = $value['active'];
            $this->di['db']->store($storage);
        }
        $this->di['db']->store($server);
        return $hardware_data;
    }


    /**
     * Test connection to server
     * 
     * @param int $id - server id
     * 
     * @return bool
     * @throws \Box_Exception 
     */
    public function server_test_connection($data)
    {
        $required = array(
            'id'    => 'Server id is missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);


        $server = $this->di['db']->getExistingModelById('service_proxmox_server', $data['id'], 'Server not found');

        if ($this->getService()->test_connection($server)) {
            $this->get_hardware_data($data['id']);
            return true;
        } else {
            return false;
        }
    }

    // Function to prepare pve setup
    public function server_prepare_pve_setup($data)
    {
        $required = array(
            'id'    => 'Server id is missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $server = $this->di['db']->getExistingModelById('service_proxmox_server', $data['id'], 'Server not found');
        $updatedserver = $this->getService()->prepare_pve_setup($server);
        if ($updatedserver) {
            $this->di['db']->store($updatedserver);
            return true;
        } else {
            return false;
        }
    }

    // Function to prepare pve setup
    public function test_access($data)
    {
        $required = array(
            'id'    => 'Server id is missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $server = $this->di['db']->getExistingModelById('service_proxmox_server', $data['id'], 'Server not found');

        if ($this->getService()->test_access($server)) {
            return true;
        } else {
            return false;
        }
    }

    /* ################################################################################################### */
    /* ##########################################  Storage  ############################################## */
    /* ################################################################################################### */


    public function storage_get($data)
    {
        // Retrieve associated storage
        $storage  = $this->di['db']->findOne('service_proxmox_storage', 'id=:id', array(':id' => $data['storage_id']));

        $output = array(
            'id'                => $storage->id,
            'name'              => $storage->name,
            'server_id'         => $storage->server_id,
            'storageclass'      => $storage->storageclass,
            'storage'           => $storage->storage,
            'content'           => $storage->content,
            'type'              => $storage->type,
            'active'            => $storage->active,
            'size'              => $storage->size,
            'used'              => $storage->used,
            'free'              => $storage->free,
            'percent_used'      => round($storage->used / $storage->size * 100, 2),
            // add list of storage classes
            'storageclasses'    => $this->storageclass_get_list($data),
        );

        return $output;
    }

    // function to update storage with storageclass
    public function storage_update($data)
    {
        $required = array(
            'id'    => 'Storage id is missing',
            'storageclass'    => 'Storage class is missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        // Retrieve associated storage
        $storage = $this->di['db']->findOne('service_proxmox_storage', 'id=:id', array(':id' => $data['id']));
        $storage->storageclass = $data['storageclass'];
        $this->di['db']->store($storage);

        return true;
    }




    /*
		Update product informations
	*/
    public function product_update($data)
    {
        $required = array(
            'id'            => 'Product id is missing',
            'group'         => 'Server group is missing',
            'filling'       => 'Filling method is missing',
            'show_stock'    => 'Stock display is missing',
            'virt'          => 'Virtualization type is missing',
            'storage'       => 'Target storage is missing',
            'memory'        => 'Memory is missing',
            'cpu'           => 'CPU cores is missing',
            'network'       => 'Network net0 is missing',
            'ide0'          => 'Storage ide0 is missing',
            'clone'         => 'Clone info is missing'
        );

        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        // Retrieve associated product
        $product  = $this->di['db']->findOne('product', 'id=:id', array(':id' => $data['id']));

        $config = array(
            'group'         => $data['group'],
            'filling'       => $data['filling'],
            'show_stock'    => $data['show_stock'],
            'virt'          => $data['virt'],
            'storage'       => $data['storage'],
            'ostemplate'    => $data['ostemplate'],
            'cdrom'         => $data['cdrom'],
            'memory'        => $data['memory'],
            'cpu'           => $data['cpu'],
            'network'       => $data['network'],
            'ide0'          => $data['ide0'],
            'clone'         => $data['clone'],
            'cloneid'       => $data['cloneid']
        );

        $product->config         = json_encode($config);
        $product->updated_at    = date('Y-m-d H:i:s');
        $this->di['db']->store($product);

        $this->di['logger']->info('Update Proxmox product %s', $product->id);
        return true;
    }

    /* ################################################################################################### */
    /* ####################################  Resource Management  ######################################## */
    /* ################################################################################################### */


    /* ################################################################################################### */
    /* ########################################  Permissions  ############################################ */
    /* ################################################################################################### */
} // EOF