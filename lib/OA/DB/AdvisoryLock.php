<?php

/*
+---------------------------------------------------------------------------+
| Openads v2.3                                                              |
| ============                                                              |
|                                                                           |
| Copyright (c) 2003-2007 Openads Limited                                   |
| For contact details, see: http://www.openads.org/                         |
|                                                                           |
| This program is free software; you can redistribute it and/or modify      |
| it under the terms of the GNU General Public License as published by      |
| the Free Software Foundation; either version 2 of the License, or         |
| (at your option) any later version.                                       |
|                                                                           |
| This program is distributed in the hope that it will be useful,           |
| but WITHOUT ANY WARRANTY; without even the implied warranty of            |
| MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the             |
| GNU General Public License for more details.                              |
|                                                                           |
| You should have received a copy of the GNU General Public License         |
| along with this program; if not, write to the Free Software               |
| Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA |
+---------------------------------------------------------------------------+
$Id$
*/

require_once MAX_PATH . '/lib/OA/DB.php';
require_once 'MDB2.php';


/**
 * Generic lock type
 */
define('OA_DB_ADVISORYLOCK_GENERIC',      '0');

/**
 * Maintenance lock type
 */
define('OA_DB_ADVISORYLOCK_MAINTENANCE',  '1');

/**
 * Distributed lock type
 */
define('OA_DB_ADVISORYLOCK_DISTRIBUTED',  '2');


/**
 * An abstract class defining the interface for using advisory locks inside Openads.
 *
 * @package    OpenadsDB
 * @subpackage AdvisoryLock
 * @author     Matteo Beccati <matteo.beccati@openads.org>
 */
class OA_DB_AdvisoryLock
{
    /**
     * An instance of the OA_DB class.
     *
     * @var OA_DB
     */
    var $oDbh;

    /**
     * The lock ID
     *
     * @access protected
     * @var string
     */
    var $_sId;

    /**
     * The class constructor method.
     *
     * @return OA_DB_AdvisoryLock
     */
    function OA_DB_AdvisoryLock()
    {
        $this->oDbh =& OA_DB::singleton();
    }

    /**
     * A factory method which returns the currently supported best advisory lock
     * instance.
     *
     * @return OA_DB_AdvisoryLock Reference to an OA_DB_AdvisoryLock object.
     */
    function &factory($sType = null)
    {
        if (is_null($sType)) {
            $oDbh  =& OA_DB::singleton();

            $aDsn  = MDB2::parseDSN($oDbh->getDSN());
            $sType = $aDsn['phptype'];
        }

        include_once(MAX_PATH.'/lib/OA/DB/AdvisoryLock/'.$sType.'.php');
        $sClass = "OA_DB_AdvisoryLock_".$sType;

        $oLock =& new $sClass();

        if (!$oLock->_isLockingSupported()) {
            // Fallback to file based locking if the current class won't work
            $oLock =& OA_DB_AdvisoryLock::factory('file');
        }

        return $oLock;
    }

    /**
     * A method to acquire an advisory lock.
     *
     * @param string $sType Lock type.
     * @param int $iWaitTime Wait time.
     * @return bool True if lock was correctly acquired.
     */
    function get($sType = OA_DB_ADVISORYLOCK_GENERIC, $iWaitTime = 0)
    {
        // Release previous lock, if any
        $this->release();

        // Generate new id
        $this->_sId = $this->_getId($sType);

        return $this->_getLock($iWaitTime);
    }

    /**
     * A method to release a previously acquired lock.
     *
     * @return bool True if lock was correctly released.
     */
    function release()
    {
        if (!empty($this->_sId)) {
            return $this->_releaseLock();
        }

        return false;
    }

    /**
     * A method to check if the lock id matches.
     *
     * @param string $sType Lock type.
     * @return bool True if locks match.
     */
    function hasSameId($sType)
    {
        return $this->_getId($sType) == $this->_sId;
    }

    /**
     * A private method to ensure that the class implementation of advisory
     * locks is supported.
     *
     * Note: PostgreSQL has advisory locks in-core since 8.2, we may need to
     * check the DB version or other things.
     *
     * @return boolean True if the current class will work
     */
    function _isLockingSupported() {
        return true;
    }

    /**
     * A private method to acquire an advisory lock.
     *
     * @param int $iWaitTime Wait time.
     * @return bool True if lock was correctly acquired.
     */
    function _getLock($iWaitTime)
    {
        MAX::debug('Base class cannot be used directly, use the factory method instead', PEAR_LOG_ERR);
        return false;
    }

    /**
     * A private method to release a previously acquired lock.
     *
     * @return bool True if the lock was correctly released.
     */
    function _releaseLock()
    {
        MAX::debug('Base class cannot be used directly, use the factory method instead', PEAR_LOG_ERR);
        return false;
    }

    /**
     * A method to generate a lock id.
     *
     * @access protected
     *
     * @param string The lock name.
     * @return string The lock id.
     */
    function _getId($sName)
    {
        if (isset($GLOBALS['_MAX']['PREF']['instance_id'])) {
            $sId = $GLOBALS['_MAX']['PREF']['instance_id'];
        } else {
            $conf = $GLOBALS['_MAX']['CONF'];
            $sId = sha1($this->oDbh->getDsn().'/'.$conf['table']['prefix']);
        }

        return "OA_{$sName}.{$sId}";
    }
}

?>
