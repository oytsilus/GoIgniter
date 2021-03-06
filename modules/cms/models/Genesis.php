<?php
namespace Modules\Cms\Models;

use \Modules\Cms\Mutator;

class Genesis extends \CI_Model
{

    // mark file
    protected $_mark_file = '';

    // The configuration file, set on construct
    protected $_config_file = '';

    // configuration values
    protected $_configs = array();

    protected $_database = NULL;

    // constructor
    public function __construct()
    {
        $this->_mark_file = MODULEPATH.'cms/.genesis';
        $this->_config_file = MODULEPATH.'cms/configs/configuration.json';

        // set some default values
        $this->_configs = array(
            'asset_url' => asset_url(),
            'hostname' => $_SERVER['SERVER_NAME'],
            'index_page' => 'index.php',
            'encryption_key' => md5(date('YmdHis')),
            'sess_cookie_name' => substr(md5(date('YmdHis')), 0, 10),
            'db.default.dsn' => '',
            'db.default.hostname' => 'localhost',
            'db.default.username' => 'root',
            'db.default.password' => '',
            'db.default.database' => '',
            'db.default.dbdriver' => 'mysqli',
            'db.default.dbprefix' => 'go_',
            'db.default.pconnect' => TRUE,
            'migration.migration_enabled' => TRUE,
        );

    }

    public function set_config($key, $value)
    {
        $this->_configs[$key] = $value;
    }

    public function set_db_config($key, $value)
    {
        $this->set_config('db.default.'.$key, $value);
    }

    public function is_set()
    {
        return file_exists($this->_mark_file);
    }

    public function load_db()
    {
        $config = array();
        foreach($this->_configs as $key => $val)
        {
            if(substr($key, 0, 11) == 'db.default.')
            {
                $config[substr($key,11)] = $val;
            }
        }
        // surpress any error, we test connection's validity by using is_db_valid
        $this->_database = @$this->load->database($config, TRUE);
        return $this->_database;
    }

    public function is_db_valid()
    {
        $this->load_db();
        return $this->_database->conn_id != FALSE;
    }

    public function setup()
    {
        if(!$this->is_set() && $this->is_db_valid())
        {
            // summon mutation
            $mutator = new Mutator();
            if(!$mutator->is_mutation_performed())
            {
                $mutation_success = $mutator->do_mutation();
                if(!$mutation_success)
                {
                    return FALSE;
                }
            }

            // write cms config
            $cms_config_file = MODULEPATH.'cms/json/configuration.json';
            if(is_writable(dirname($cms_config_file)))
            {
                file_put_contents($cms_config_file, json_encode($this->_configs));
                if(function_exists('opcache_invalidate'))
                {
                    opcache_invalidate($cms_config_file);
                }

                // unset any previously created $CI->db
                $CI =& get_instance();
                unset($CI->db);

                // reload database with newly created configuration
                $CI->db = $this->load_db();

                // prepare migration
                $module_migrator = new \Module_Migrator();
                $module_migrator->migrate('cms');

                file_put_contents($this->_mark_file, 'Genesis set on ' . date('Y-m-d H:i:s'));

                return TRUE;
            }

        }
        return FALSE;
    }

}
