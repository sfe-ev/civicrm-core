<?php

/*
 +--------------------------------------------------------------------+
| CiviCRM version 4.4                                                |
+--------------------------------------------------------------------+
| Copyright CiviCRM LLC (c) 2004-2013                                |
+--------------------------------------------------------------------+
| This file is a part of CiviCRM.                                    |
|                                                                    |
| CiviCRM is free software; you can copy, modify, and distribute it  |
| under the terms of the GNU Affero General Public License           |
| Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
|                                                                    |
| CiviCRM is distributed in the hope that it will be useful, but     |
| WITHOUT ANY WARRANTY; without even the implied warranty of         |
| MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
| See the GNU Affero General Public License for more details.        |
|                                                                    |
| You should have received a copy of the GNU Affero General Public   |
| License and the CiviCRM Licensing Exception along                  |
| with this program; if not, contact CiviCRM LLC                     |
| at info[AT]civicrm[DOT]org. If you have questions about the        |
| GNU Affero General Public License or the licensing of CiviCRM,     |
| see the CiviCRM license FAQ at http://civicrm.org/licensing        |
+--------------------------------------------------------------------+
*/

/**
 *  Include class definitions
 */
require_once 'tests/phpunit/CiviTest/CiviUnitTestCase.php';

/**
 *  Test APIv3 civicrm_profile_* functions
 *
 *  @package   CiviCRM
 */
class api_v3_ProfileTest extends CiviUnitTestCase {
  protected $_apiversion;
  function get_info() {
    return array(
      'name' => 'Profile Test',
      'description' => 'Test all profile API methods.',
      'group' => 'CiviCRM API Tests',
    );
  }

  function setUp() {
    $this->_apiversion = 3;
    parent::setUp();
    $config = CRM_Core_Config::singleton();
    $config->countryLimit[1] = 1013;
    $config->stateLimit[1] = 1013;
  }

  function tearDown() {

    $this->quickCleanup(array(
        'civicrm_contact',
        'civicrm_phone',
        'civicrm_address',
      ), TRUE);
    // ok can't be bothered wring an api to do this & truncating is crazy
    CRM_Core_DAO::executeQuery(' DELETE FROM civicrm_uf_group WHERE id IN (25, 26)');
  }

  ////////////// test $this->callAPISuccess3_profile_get //////////////////

  /**
   * check Without ProfileId
   */
  function testProfileGetWithoutProfileId() {
    $params = array(
      'contact_id' => 1,
    );
    $result = $this->callAPIFailure('profile', 'get', $params,
      'Mandatory key(s) missing from params array: profile_id'
    );
  }

  /**
   * check with no invalid profile Id
   */
  function testProfileGetInvalidProfileId() {
    $params = array(
      'contact_id' => 1,
      'profile_id' => 1000,
    );
    $result = $this->callAPIFailure('profile', 'get', $params);
  }

  /**
   * check with success
   */
  function testProfileGet() {
    $pofileFieldValues = $this->_createIndividualContact();
    $expected          = current($pofileFieldValues);
    $contactId         = key($pofileFieldValues);
    $params            = array(
      'profile_id' => 25,
      'contact_id' => $contactId,
    );
    $result = $this->callAPISuccess('profile', 'get', $params);
    foreach ($expected as $profileField => $value) {
      $this->assertEquals($value, CRM_Utils_Array::value($profileField, $result['values']), "In line " . __LINE__ . " error message: " . "missing/mismatching value for {$profileField}"
      );
    }
  }

  function testProfileGetMultiple() {
    $pofileFieldValues = $this->_createIndividualContact();
    $expected          = current($pofileFieldValues);
    $contactId         = key($pofileFieldValues);
    $params            = array(
      'profile_id' => array(25, 1, 'Billing'),
      'contact_id' => $contactId,
    );

    $result = $this->callAPIAndDocument('profile', 'get', $params, __FUNCTION__, __FILE__);
    foreach ($expected as $profileField => $value) {
      $this->assertEquals($value, CRM_Utils_Array::value($profileField, $result['values'][25]), " error message: " . "missing/mismatching value for {$profileField}");
    }
    $this->assertEquals('abc1', $result['values'][1]['first_name'], " error message: " . "missing/mismatching value for {$profileField}");
    $this->assertFalse(array_key_exists('email-Primary', $result['values'][1]), 'profile 1 doesn not include email');
    $this->assertEquals($result['values']['Billing'], array(
      'billing_first_name' => 'abc1',
      'billing_middle_name' => '',
      'billing_last_name' => 'xyz1',
      'billing_street_address-5' => '',
      'billing_city-5' => '',
      'billing_state_province_id-5' => '',
      'billing_country_id-5' => '',
      'billing-email-5' => 'abc1.xyz1@yahoo.com',
    ));
  }

  function testProfileGetMultipleHasBillingLocation() {
    $individual = $this->_createIndividualContact();
    $contactId  = key($individual);
    $this->callAPISuccess('address', 'create', array('contact_id' => $contactId , 'street_address' => '25 Big Street', 'city' => 'big city', 'location_type_id' => 5));
    $this->callAPISuccess('email', 'create', array('contact_id' => $contactId , 'email' => 'big@once.com', 'location_type_id' => 2, 'is_billing' => 1));

    $expected = current($individual);

    $params = array(
      'profile_id' => array(25, 1, 'Billing'),
      'contact_id' => $contactId,
    );

    $result = $this->callAPISuccess('profile', 'get', $params, __FUNCTION__, __FILE__);
    $this->assertEquals('abc1', $result['values'][1]['first_name']);
    $this->assertEquals($result['values']['Billing'], array(
      'billing_first_name' => 'abc1',
      'billing_middle_name' => '',
      'billing_last_name' => 'xyz1',
      'billing_street_address-5' => '25 Big Street',
      'billing_city-5' => 'big city',
      'billing_state_province_id-5' => '',
      'billing_country_id-5' => '',
      'billing-email-5' => 'big@once.com',
    ));
  }
  /**
   * check contact activity profile without activity id
   */
  function testContactActivityGetWithoutActivityId() {
    list($params, $expected) = $this->_createContactWithActivity();

    unset($params['activity_id']);
    $result = $this->callAPIFailure('profile', 'get', $params,
      'Mandatory key(s) missing from params array: activity_id');
  }

  /**
   * check contact activity profile wrong activity id
   */
  function testContactActivityGetWrongActivityId() {
    list($params, $expected) = $this->_createContactWithActivity();

    $params['activity_id'] = 100001;
    $result = $this->callAPIFailure('profile', 'get', $params,
       'Invalid Activity Id (aid).');
  }

  /*
     * check contact activity profile with wrong activity type
     */
  function testContactActivityGetWrongActivityType() {
    //flush cache by calling with reset
    $activityTypes = CRM_Core_PseudoConstant::activityType(TRUE, TRUE, TRUE, 'name', TRUE);

    $sourceContactId = $this->householdCreate();

    $activityparams = array(
      'source_contact_id' => $sourceContactId,
      'activity_type_id' => '2',
      'subject' => 'Test activity',
      'activity_date_time' => '20110316',
      'duration' => '120',
      'location' => 'Pensulvania',
      'details' => 'a test activity',
      'status_id' => '1',
      'priority_id' => '1',
    );

    $activity = $this->callAPISuccess('activity', 'create', $activityparams);

    $activityValues = array_pop($activity['values']);

    list($params, $expected) = $this->_createContactWithActivity();

    $params['activity_id'] = $activityValues['id'];
    $result = $this->callAPIFailure('profile', 'get', $params,
      'This activity cannot be edited or viewed via this profile.'
    );
  }

  /*
     * check contact activity profile with success
     */
  function testContactActivityGetSuccess() {
    list($params, $expected) = $this->_createContactWithActivity();

    $result = $this->callAPISuccess('profile', 'get', $params);

    foreach ($expected as $profileField => $value) {
      $this->assertEquals($value, CRM_Utils_Array::value($profileField, $result['values']), "In line " . __LINE__ . " error message: " . "missing/mismatching value for {$profileField}"
      );
    }
  }

  /////////////// test $this->callAPISuccess3_profile_set //////////////////

  /**
   * check with no array
   */
  function testProfileSetNoArray() {
    $params = NULL;
    $result = $this->callAPIFailure('profile', 'set', $params);
    $this->assertEquals($result['error_message'], 'Input variable `params` is not an array');
  }

  /**
   * check Without ProfileId
   */
  function testProfileSetWithoutProfileId() {
    $params = array(
      'contact_id' => 1,
    );
    $result = $this->callAPIFailure('profile', 'set', $params,
      'Mandatory key(s) missing from params array: profile_id'
    );
  }

  /**
   * check with no invalid profile Id
   */
  function testProfileSetInvalidProfileId() {
    $params = array(
      'contact_id' => 1,
      'profile_id' => 1000,
    );
    $result = $this->callAPIFailure('profile', 'set', $params);
  }

  /**
   * check with missing required field in profile
   */
  function testProfileSetCheckProfileRequired() {
    $pofileFieldValues = $this->_createIndividualContact();
    current($pofileFieldValues);
    $contactId = key($pofileFieldValues);
    $updateParams = array(
      'first_name' => 'abc2',
      'last_name' => 'xyz2',
      'phone-1-1' => '022 321 826',
      'country-1' => '1013',
      'state_province-1' => '1000',
    );

    $params = array_merge(array('profile_id' => 25, 'contact_id' => $contactId),
      $updateParams
    );

    $result = $this->callAPIFailure('profile', 'set', $params,
      'Missing required parameters for profile id 25: email-Primary'
    );
  }

  /**
   * check with success
   */
  function testProfileSet() {
    $pofileFieldValues = $this->_createIndividualContact();
    current($pofileFieldValues);
    $contactId = key($pofileFieldValues);

    $updateParams = array(
      'first_name' => 'abc2',
      'last_name' => 'xyz2',
      'email-Primary' => 'abc2.xyz2@gmail.com',
      'phone-1-1' => '022 321 826',
      'country-1' => '1013',
      'state_province-1' => '1000',
    );

    $params = array_merge(array(
        'profile_id' => 25,
        'contact_id' => $contactId,
      ), $updateParams);

    $result = $this->callAPIAndDocument('profile', 'set', $params, __FUNCTION__, __FILE__);

    $getParams = array(
      'profile_id' => 25,
      'contact_id' => $contactId,
    );
    $profileDetails = $this->callAPISuccess('profile', 'get', $getParams);

    foreach ($updateParams as $profileField => $value) {
      $this->assertEquals($value, CRM_Utils_Array::value($profileField, $profileDetails['values']), "In line " . __LINE__ . " error message: " . "missing/mismatching value for {$profileField}"
      );
    }
  }

  /*
     * check contact activity profile without activity id
     */
  function testContactActivitySetWithoutActivityId() {
    list($params, $expected) = $this->_createContactWithActivity();

    $params = array_merge($params, $expected);
    unset($params['activity_id']);
    $result = $this->callAPIFailure('profile', 'set', $params);
    $this->assertEquals($result['error_message'], 'Mandatory key(s) missing from params array: activity_id');

    $this->quickCleanup(array('civicrm_uf_field', 'civicrm_uf_join', 'civicrm_uf_group', 'civicrm_custom_field', 'civicrm_custom_group', 'civicrm_contact'));
  }

  /*
     * check contact activity profile wrong activity id
     */
  function testContactActivitySetWrongActivityId() {
    list($params, $expected) = $this->_createContactWithActivity();

    $params = array_merge($params, $expected);
    $params['activity_id'] = 100001;
    $result = $this->callAPIFailure('profile', 'set', $params);
    $this->assertEquals($result['error_message'], 'Invalid Activity Id (aid).');

    $this->quickCleanup(array('civicrm_uf_field', 'civicrm_uf_join', 'civicrm_uf_group', 'civicrm_custom_field', 'civicrm_custom_group', 'civicrm_contact'));
  }

  /*
     * check contact activity profile with wrong activity type
     */
  function testContactActivitySetWrongActivityType() {
    //flush cache by calling with reset
    $activityTypes = CRM_Core_PseudoConstant::activityType(TRUE, TRUE, TRUE, 'name', TRUE);

    $sourceContactId = $this->householdCreate();

    $activityparams = array(
      'source_contact_id' => $sourceContactId,
      'activity_type_id' => '2',
      'subject' => 'Test activity',
      'activity_date_time' => '20110316',
      'duration' => '120',
      'location' => 'Pensulvania',
      'details' => 'a test activity',
      'status_id' => '1',
      'priority_id' => '1',
    );

    $activity = $this->callAPISuccess('activity', 'create', $activityparams);

    $activityValues = array_pop($activity['values']);

    list($params, $expected) = $this->_createContactWithActivity();

    $params = array_merge($params, $expected);
    $params['activity_id'] = $activityValues['id'];
    $result = $this->callAPIFailure('profile', 'set', $params,
      'This activity cannot be edited or viewed via this profile.');
  }

  /*
     * check contact activity profile with success
     */
  function testContactActivitySetSuccess() {
    list($params, $expected) = $this->_createContactWithActivity();

    $updateParams = array(
      'first_name' => 'abc2',
      'last_name' => 'xyz2',
      'email-Primary' => 'abc2.xyz2@yahoo.com',
      'activity_subject' => 'Test Meeting',
      'activity_details' => 'a test activity details',
      'activity_duration' => '100',
      'activity_date_time' => '03/08/2010',
      'activity_status_id' => '2',
    );
    $profileParams = array_merge($params, $updateParams);
    $profile       = $this->callAPISuccess('profile', 'set', $profileParams);
    $result        = $this->callAPISuccess('profile', 'get', $params);

    foreach ($updateParams as $profileField => $value) {
      $this->assertEquals($value, CRM_Utils_Array::value($profileField, $result['values']), "In line " . __LINE__ . " error message: " . "missing/mismatching value for {$profileField}"
      );
    }
  }

  /**
   * check profile apply Without ProfileId
   */
  function testProfileApplyWithoutProfileId() {
    $params = array(
      'contact_id' => 1,
    );
    $result = $this->callAPIFailure('profile', 'apply', $params,
      'Mandatory key(s) missing from params array: profile_id');
  }

  /**
   * check profile apply with no invalid profile Id
   */
  function testProfileApplyInvalidProfileId() {
    $params = array(
      'contact_id' => 1,
      'profile_id' => 1000,
    );
    $result = $this->callAPIFailure('profile', 'apply', $params);
  }

  /**
   * check with success
   */
  function testProfileApply() {
    $pofileFieldValues = $this->_createIndividualContact();
    current($pofileFieldValues);
    $contactId = key($pofileFieldValues);

    $params = array(
      'profile_id' => 25,
      'contact_id' => $contactId,
      'first_name' => 'abc2',
      'last_name' => 'xyz2',
      'email-Primary' => 'abc2.xyz2@gmail.com',
      'phone-1-1' => '022 321 826',
      'country-1' => '1013',
      'state_province-1' => '1000',
    );

    $result = $this->callAPIAndDocument('profile', 'apply', $params, __FUNCTION__, __FILE__);

    // Expected field values
    $expected['contact'] = array(
      'contact_id' => $contactId,
      'contact_type' => 'Individual',
      'first_name' => 'abc2',
      'last_name' => 'xyz2',
    );
    $expected['email'] = array(
      'location_type_id' => 1,
      'is_primary' => 1,
      'email' => 'abc2.xyz2@gmail.com',
    );

    $expected['phone'] = array(
      'location_type_id' => 1,
      'is_primary' => 1,
      'phone_type_id' => 1,
      'phone' => '022 321 826',
    );
    $expected['address'] = array(
      'location_type_id' => 1,
      'is_primary' => 1,
      'country_id' => 1013,
      'state_province_id' => 1000,
    );

    foreach ($expected['contact'] as $field => $value) {
      $this->assertEquals($value, CRM_Utils_Array::value($field, $result['values']), "In line " . __LINE__ . " error message: " . "missing/mismatching value for {$field}"
      );
    }

    foreach (array(
      'email', 'phone', 'address') as $fieldType) {
      $typeValues = array_pop($result['values'][$fieldType]);
      foreach ($expected[$fieldType] as $field => $value) {
        $this->assertEquals($value, CRM_Utils_Array::value($field, $typeValues), "In line " . __LINE__ . " error message: " . "missing/mismatching value for {$field} ({$fieldType})"
        );
      }
    }
  }

  /*
     * Helper function to create an Individual with address/email/phone info. Import UF Group and UF Fields
     */
  function _createIndividualContact() {
    $contactParams = array(
      'first_name' => 'abc1',
      'last_name' => 'xyz1',
      'contact_type' => 'Individual',
      'email' => 'abc1.xyz1@yahoo.com',
      'api.address.create' => array(
        'location_type_id' => 1,
        'is_primary' => 1,
        'name' => 'Saint Helier St',
        'county' => 'Marin',
        'country' => 'United States',
        'state_province' => 'Michigan',
        'supplemental_address_1' => 'Hallmark Ct',
        'supplemental_address_2' => 'Jersey Village',
      ),
      'api.phone.create' => array(
        'location_type_id' => '1',
        'phone' => '021 512 755',
        'phone_type_id' => '1',
        'is_primary' => '1',
      ),
    );

    $contact = $this->callAPISuccess('contact', 'create', $contactParams);

    $keys  = array_keys($contact['values']);
    $contactId = array_pop($keys);

    $this->assertEquals(0, $contact['values'][$contactId]['api.address.create']['is_error'], "In line " . __LINE__ . " error message: " . CRM_Utils_Array::value('error_message', $contact['values'][$contactId]['api.address.create'])
    );
    $this->assertEquals(0, $contact['values'][$contactId]['api.phone.create']['is_error'], "In line " . __LINE__ . " error message: " . CRM_Utils_Array::value('error_message', $contact['values'][$contactId]['api.phone.create'])
    );

    // Create new profile having group_type: Contact,Individual
    $op = new PHPUnit_Extensions_Database_Operation_Insert();
    $op->execute($this->_dbconn,
      new PHPUnit_Extensions_Database_DataSet_XMLDataSet(
        dirname(__FILE__) . "/dataset/uf_group_25.xml"
      )
    );
    // Create Contact + Idividual fields for profile
    $op = new PHPUnit_Extensions_Database_Operation_Insert();
    $op->execute($this->_dbconn,
      new PHPUnit_Extensions_Database_DataSet_XMLDataSet(
        dirname(__FILE__) . "/dataset/uf_field_uf_group_25.xml"
      )
    );


    // expected result of above created profile with contact Id $contactId
    $profileData[$contactId] = array(
      'first_name' => 'abc1',
      'last_name' => 'xyz1',
      'email-Primary' => 'abc1.xyz1@yahoo.com',
      'phone-1-1' => '021 512 755',
      'country-1' => '1228',
      'state_province-1' => '1021',
    );

    return $profileData;
  }

  function _createContactWithActivity() {
    // @TODO: Create profile with custom fields
    $op = new PHPUnit_Extensions_Database_Operation_Insert();
    $op->execute($this->_dbconn,
      new PHPUnit_Extensions_Database_DataSet_FlatXMLDataSet(
        dirname(__FILE__) . '/dataset/uf_group_contact_activity_26.xml'
      )
    );
    // hack: xml data set do not accept  (CRM_Core_DAO::VALUE_SEPARATOR)
    CRM_Core_DAO::setFieldValue('CRM_Core_DAO_UFGroup', '26', 'group_type', 'Individual,Contact,Activity' . CRM_Core_DAO::VALUE_SEPARATOR . 'ActivityType:1');

    $sourceContactId = $this->individualCreate();
    $contactParams = array(
      'first_name' => 'abc1',
      'last_name' => 'xyz1',
      'contact_type' => 'Individual',
      'email' => 'abc1.xyz1@yahoo.com',
      'api.address.create' => array(
        'location_type_id' => 1,
        'is_primary' => 1,
        'name' => 'Saint Helier St',
        'county' => 'Marin',
        'country' => 'United States',
        'state_province' => 'Michigan',
        'supplemental_address_1' => 'Hallmark Ct',
        'supplemental_address_2' => 'Jersey Village',
      ),
    );

    $contact = $this->callAPISuccess('contact', 'create', $contactParams);

    $keys = array_keys($contact['values']);
    $contactId = array_pop($keys);

    $this->assertEquals(0, $contact['values'][$contactId]['api.address.create']['is_error'], "In line " . __LINE__ . " error message: " . CRM_Utils_Array::value('error_message', $contact['values'][$contactId]['api.address.create'])
    );

    $activityParams = array(
      'source_contact_id' => $sourceContactId,
      'assignee_contact_id' => $contactId,
      'activity_type_id' => '1',
      'subject' => 'Make-it-Happen Meeting',
      'activity_date_time' => '20110316',
      'duration' => '120',
      'location' => 'Pensulvania',
      'details' => 'a test activity',
      'status_id' => '1',
      'priority_id' => '1',
    );
    $activity = $this->callAPISuccess('activity', 'create', $activityParams);

    $activityValues = array_pop($activity['values']);

    // valid parameters for above profile
    $profileParams = array(
      'profile_id' => 26,
      'contact_id' => $contactId,
      'activity_id' => $activityValues['id'],
         );

    // expected result of above created profile
    $expected = array(
      'first_name' => 'abc1',
      'last_name' => 'xyz1',
      'email-Primary' => 'abc1.xyz1@yahoo.com',
      'activity_subject' => 'Make-it-Happen Meeting',
      'activity_details' => 'a test activity',
      'activity_duration' => '120',
      'activity_date_time_time' => '12:00AM',
      'activity_date_time' => '03/16/2011',
      'activity_status_id' => '1',
    );

    return array($profileParams, $expected);
  }
}

