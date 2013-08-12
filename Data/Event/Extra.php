<?php

/**
 * Event
 *
 * @author Team phpManufaktur <team@phpmanufaktur.de>
 * @link https://kit2.phpmanufaktur.de/FacebookGallery
 * @copyright 2013 Ralf Hertsch <ralf.hertsch@phpmanufaktur.de>
 * @license MIT License (MIT) http://www.opensource.org/licenses/MIT
 */

namespace phpManufaktur\Event\Data\Event;

use Silex\Application;

class Extra
{

    protected $app = null;
    protected static $table_name = null;


    /**
     * Constructor
     *
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
        self::$table_name = FRAMEWORK_TABLE_PREFIX.'event_extra';
    }

    /**
     * Create the EVENT table
     *
     * @throws \Exception
     */
    public function createTable()
    {
        $table = self::$table_name;
        $table_extra_type = FRAMEWORK_TABLE_PREFIX.'event_extra_type';
        $SQL = <<<EOD
    CREATE TABLE IF NOT EXISTS `$table` (
        `extra_id` INT(11) NOT NULL AUTO_INCREMENT,
        `extra_type_id` INT(11) DEFAULT NULL,
        `extra_type_name` VARCHAR(64) NOT NULL DEFAULT '',
        `group_id` INT(11) NOT NULL DEFAULT '-1',
        `event_id` INT(11) NOT NULL DEFAULT '-1',
        `extra_type_type` ENUM('TEXT','HTML','VARCHAR','INT','FLOAT','DATE','DATETIME') NOT NULL DEFAULT 'VARCHAR',
        `extra_text` TEXT NOT NULL,
        `extra_html` TEXT NOT NULL,
        `extra_varchar` VARCHAR(255) NOT NULL DEFAULT '',
        `extra_int` INT(11) NOT NULL DEFAULT '0',
        `extra_float` FLOAT NOT NULL DEFAULT '0',
        `extra_date` DATE NOT NULL DEFAULT '0000-00-00',
        `extra_datetime` DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
        `extra_time` TIME NOT NULL DEFAULT '00:00:00',
        `extra_timestamp` TIMESTAMP,
        PRIMARY KEY (`extra_id`),
        INDEX (`group_id`)
        )
    COMMENT='The table for extra fields'
    ENGINE=InnoDB
    AUTO_INCREMENT=1
    DEFAULT CHARSET=utf8
    COLLATE='utf8_general_ci'
EOD;
        try {
            $this->app['db']->query($SQL);
            $this->app['monolog']->addInfo("Created table 'event_extra'", array(__METHOD__, __LINE__));
        } catch (\Doctrine\DBAL\DBALException $e) {
            throw new \Exception($e);
        }
    }


    public function selectByGroupID($group_id)
    {
        try {
            $SQL = "SELECT * FROM `".self::$table_name."` WHERE `group_id`='$group_id'";
            $results = $this->app['db']->fetchAll($SQL);
            $extras = array();
            foreach ($results as $extra) {
                $record = array();
                foreach ($extra as $key => $value) {
                    $record[$key] = is_string($value) ? $this->app['utils']->unsanitizeText($value) : $value;
                }
                $extras[] = $record;
            }
            return $extras;
        } catch (\Doctrine\DBAL\DBALException $e) {
            throw new \Exception($e);
        }
    }
    
    /**
     * Insert a new extra record
     *
     * @param array $data
     * @param reference integer $extra_id
     * @throws \Exception
     */
    public function insert($data, &$extra_id=null)
    {
        try {
            $insert = array();
            foreach ($data as $key => $value) {
                if (($key == 'extra_id') || ($key == 'extra_timestamp')) continue;
                $insert[$this->app['db']->quoteIdentifier($key)] = is_string($value) ? $this->app['utils']->unsanitizeText($value) : $value;
            }
            $this->app['db']->insert(self::$table_name, $insert);
            $extra_id = $this->app['db']->lastInsertId();
        } catch (\Doctrine\DBAL\DBALException $e) {
            throw new \Exception($e);
        }
    }
    
    public function selectByEventID($event_id)
    {
        try {
            $SQL = "SELECT * FROM `".self::$table_name."` WHERE `event_id`='$event_id'";
            $results = $this->app['db']->fetchAll($SQL);
            $extras = array();
            foreach ($results as $extra) {
                $record = array();
                foreach ($extra as $key => $value) {
                    $record[$key] = is_string($value) ? $this->app['utils']->unsanitizeText($value) : $value;
                }
                $extras[] = $record;
            }
            return $extras;
        } catch (\Doctrine\DBAL\DBALException $e) {
            throw new \Exception($e);
        }
    }

}