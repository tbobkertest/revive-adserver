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
$Id $
*/


require_once MAX_PATH.'/lib/OA/DB.php';
require_once MAX_PATH.'/lib/OA/DB/Table.php';
require_once MAX_PATH.'/lib/OA/Upgrade/DB_Upgrade.php';


/**
 * A class for testing the Openads_DB_Upgrade class.
 *
 * @package    Openads Upgrade
 * @subpackage TestSuite
 * @author     Monique Szpak <monique.szpak@openads.org>
 */
class Test_DB_Upgrade extends UnitTestCase
{
    /**
     * The constructor method.
     */
    function Test_DB_Upgrade()
    {
        $this->UnitTestCase();
    }

    function test_prepPreScript()
    {
        $oDB_Upgrade = $this->_newDBUpgradeObject();
        $this->assertTrue($oDB_Upgrade->prepPreScript(MAX_PATH.'/lib/OA/Upgrade/tests/data/prescript_tables_core_2.php'));
        $this->assertIsA($oDB_Upgrade->oPreScript, 'prescript_tables_core_2', 'prescript class instance error');
    }

    function test_runPreScript()
    {
        $oDB_Upgrade = $this->_newDBUpgradeObject();
        $this->assertTrue($oDB_Upgrade->prepPreScript(MAX_PATH.'/lib/OA/Upgrade/tests/data/prescript_tables_core_2.php'));
        $this->assertTrue($oDB_Upgrade->runPreScript(true),'prescript execution error');
        $this->assertFalse($oDB_Upgrade->runPreScript(false),'prescript execution error');
    }

    function test_prepPostScript()
    {
        $oDB_Upgrade = $this->_newDBUpgradeObject();
        $this->assertTrue($oDB_Upgrade->prepPostScript(MAX_PATH.'/lib/OA/Upgrade/tests/data/postscript_tables_core_2.php'));
        $this->assertIsA($oDB_Upgrade->oPostScript, 'postscript_tables_core_2', 'postscript class instance error');
    }

    function test_runPostScript()
    {
        $oDB_Upgrade = $this->_newDBUpgradeObject();
        $this->assertTrue($oDB_Upgrade->prepPostScript(MAX_PATH.'/lib/OA/Upgrade/tests/data/postscript_tables_core_2.php'));
        $this->assertTrue($oDB_Upgrade->runPostScript(true),'postscript execution error');
        $this->assertFalse($oDB_Upgrade->runPostScript(false),'postscript execution error');
    }

    /**
     * a problem with mdb2_schema is that field definitions are held in arrays that are not ordered
     * this is a problem when it comes to creating a multi-key index that must be ordered properly
     * mdb2_schema will be patched to define an 'order' key for an index field definition
     * this method sorts the fields into the right order
     *
     */
    function test_sortIndexFields()
    {
        $fields = 'B_field1, E_field2, A_field3, D_field4, C_field5';
        $aFields = explode(',', $fields);
        $aResult = array('fields'=> array(
                                            $aFields[3]=>array('order'=>'4','sorting'=>'ascending'),
                                            $aFields[1]=>array('order'=>'2','sorting'=>'ascending'),
                                            $aFields[4]=>array('order'=>'5','sorting'=>'ascending'),
                                            $aFields[2]=>array('order'=>'3','sorting'=>'ascending'),
                                            $aFields[0]=>array('order'=>'1','sorting'=>'ascending'),
                                            )
                         );
        $oDB_Upgrade = $this->_newDBUpgradeObject();
        $aResult = $oDB_Upgrade->_sortIndexFields($aResult);
        $i = 0;
        foreach ($aResult['fields'] AS $field_name => $field_def)
        {
            $this->assertEqual($field_name,$aFields[$i],'field in wrong position');
            $i++;
        }
    }

    function test_getPreviousTablename()
    {
        $oDB_Upgrade = $this->_newDBUpgradeObject();
        $oDB_Upgrade->aChanges  = $oDB_Upgrade->oSchema->parseChangesetDefinitionFile(MAX_PATH.'/lib/OA/Upgrade/tests/data/changes_test_rename.xml');
        $this->assertEqual($oDB_Upgrade->_getPreviousTablename('table1_renamed'), 'table1', 'wrong previous table name');
    }

    function test_getPreviousFieldname()
    {
        $oDB_Upgrade = $this->_newDBUpgradeObject();
        $oDB_Upgrade->aChanges  = $oDB_Upgrade->oSchema->parseChangesetDefinitionFile(MAX_PATH.'/lib/OA/Upgrade/tests/data/changes_test_rename.xml');
        $this->assertEqual($oDB_Upgrade->_getPreviousFieldname('table1', 'b_id_field_renamed'), 'b_id_field', 'wrong previous field name');
    }

    function _newDBUpgradeObject($timing='constructive')
    {
        $oDB_Upgrade = & new OA_DB_Upgrade();
        $oDB_Upgrade->initMDB2Schema();
        $oDB_Upgrade->timingStr = $timing;
        $oDB_Upgrade->timingInt = ($timing ? 0 : 1);
        $oDB_Upgrade->schema = 'tables_core';
        $oDB_Upgrade->prefix = '';
        $oDB_Upgrade->versionFrom = 1;
        $oDB_Upgrade->versionTo = 2;
        $oDB_Upgrade->logFile = MAX_PATH . "/var/DB_Upgrade.dev.test.log";
        return $oDB_Upgrade;
    }

}

?>
