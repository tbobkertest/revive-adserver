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

require_once MAX_PATH . '/lib/max/Admin/Preferences.php';
require_once MAX_PATH . '/lib/max/language/Default.php';
require_once MAX_PATH . '/lib/OA.php';
require_once MAX_PATH . '/lib/OA/Dal.php';
require_once MAX_PATH . '/www/admin/lib-statistics.inc.php';
require_once 'Date.php';

/**
 * A class to provide support for sending of email-based reports and
 * alerts.
 *
 * @package    Openads
 * @author     Andrew Hill <andrew.hill@openads.org>
 */
class OA_Email
{

    /**
     * A static method for preparing an advertiser's "placement delivery" report.
     *
     * @static
     * @param integer    $advertiserId The advertiser's ID.
     * @param PEAR::Date $oStartDate   The start date of the report, inclusive.
     * @param PEAR::Date $oEndDate     The end date of the report, inclusive.
     * @return boolean|array False, if the report could not be created, otherwise
     *                       an array of four elements:
     *                          'subject'   => The email subject line.
     *                          'contents'  => The body of the email report.
     *                          'userEmail' => The email address to send the report to.
     *                          'userName'  => The real name of the email address, or null.
     *
     * @copyright 2003-2007 Openads Limited
     * @copyright 2000-2003 The phpAdsNew developers
     */
    function preparePlacementDeliveryEmail($advertiserId, $oStartDate, $oEndDate)
    {
        OA::debug('   - Preparing "placement delivery" report for advertiser ID ' . $advertiserId . '.', PEAR_LOG_DEBUG);

        $aPref = $GLOBALS['_MAX']['PREF'];
        if (is_null($aPref)) {
            $aPref = MAX_Admin_Preferences::loadPrefs();
        }

        Language_Default::load();
        global $phpAds_CharSet, $date_format, $strBanner, $strCampaign, $strImpressions,
               $strClicks, $strConversions, $strLinkedTo, $strMailSubject, $strMailHeader,
               $strMailBannerStats, $strMailFooter, $strMailReportPeriod, $strMailReportPeriodAll,
               $strLogErrorBanners, $strLogErrorClients, $strLogErrorViews, $strLogErrorClicks,
               $strLogErrorConversions, $strNoStatsForCampaign, $strNoViewLoggedInInterval,
               $strNoClickLoggedInInterval, $strNoCampaignLoggedInInterval, $strTotal,
               $strTotalThisPeriod;

        $oDbh =& OA_DB::singleton();

        // Prepare some strings & formatting options
        $strCampaignLength = strlen($strCampaign);
        $strBannerLength   = strlen($strBanner);

        $headingPrintfLength = max($strCampaignLength, $strBannerLength);
        $strCampaignPrint = '%-'  . $headingPrintfLength . 's';
        $headingPrintfLength--;
        $strBannerPrint   = ' %-'  . $headingPrintfLength . 's';

        $strTotalImpressions = $strImpressions . ' (' . $strTotal . ')';
        $strTotalClicks      = $strClicks      . ' (' . $strTotal . ')';
        $strTotalConversions = $strConversions . ' (' . $strTotal . ')';

        $strTotalImpressionsLength = strlen($strTotalImpressions);
        $strTotalClicksLength      = strlen($strTotalClicks);
        $strTotalConversionsLength = strlen($strTotalConversions);
        $strTotalThisPeriodLength  = strlen($strTotalThisPeriod);

        $adTextPrintfLength = max($strTotalImpressionsLength, $strTotalClicksLength, $strTotalConversionsLength, $strTotalThisPeriodLength);
        $adTextPrint = ' %'  . $adTextPrintfLength . 's';

        // Prepare the result array
        $aResult = array(
            'subject'   => '',
            'contents'  => '',
            'userEmail' => '',
            'userName'  => null
        );

        // Get the advertiser's details
        $doClients = OA_Dal::factoryDO('clients');
        $doClients->clientid = $advertiserId;
        $doClients->find();
        if (!$doClients->fetch()) {
            OA::debug('   - Error obtaining details for advertiser ID ' . $advertiserId . '.', PEAR_LOG_ERR);
            return false;
        }
        $aAdvertiser = $doClients->toArray();
        // Does the advertiser have an email address?
        if (empty($aAdvertiser['email'])) {
            OA::debug('   - No email for advertiser ID ' . $advertiserId . '.', PEAR_LOG_ERR);
            return false;
        }
        // Check the advertiser wants to have reports sent
        if ($aAdvertiser['report'] != 't') {
            OA::debug('   - Reports disabled for advertiser ID ' . $advertiserId . '.', PEAR_LOG_ERR);
            return false;
        }
        // Fetch all the advertiser's placements
        $doPlacements = OA_Dal::factoryDO('campaigns');
        $doPlacements->clientid = $advertiserId;
        $doPlacements->find();
        if ($doPlacements->getRowCount() > 0) {
            while ($doPlacements->fetch()) {
                $aPlacement = $doPlacements->toArray();
                $aResult['contents'] .= "\n" . sprintf($strCampaignPrint, $strCampaign) . ' ' .
                                        strip_tags(phpAds_buildName($aPlacement['campaignid'], $aPlacement['campaignname'])) . "\n";
                $aResult['contents'] .= "=======================================================\n\n";
                // Fetch all ads in the placement
                $doBanners = OA_Dal::factoryDO('banners');
                $doBanners->campaignid = $aPlacement['campaignid'];
                $doBanners->find();
                if ($doBanners->getRowCount() > 0) {
                    $adsWithDelivery = false;
                    while ($doBanners->fetch()) {
                        $aAd = $doBanners->toArray();
                        OA::debug('   - Preparing report details for ad ID ' . $aAd['bannerid'] . '.', PEAR_LOG_DEBUG);
                        // Get the total impressions, clicks and conversions delivered by this ad
                        $adImpressions = phpAds_totalViews($aAd['bannerid']);
                        $adClicks      = phpAds_totalClicks($aAd['bannerid']);
                        $adConversions = phpAds_totalConversions($aAd['bannerid']);
                        if ($adImpressions > 0 || $adClicks > 0 || $adConversions > 0) {
                            // This ad has delivered at some stage, so report on the ad for the report period
                            $aResult['contents'] .= sprintf($strBannerPrint, $strBanner) . ' ' .
                                strip_tags(phpAds_buildBannerName($aAd['bannerid'], $aAd['description'], $aAd['alt'])) . "\n";
                            if (!empty($aAd['URL'])) {
                                $aResult['contents'] .= $strLinkedTo . ': ' . $aAd['URL'] . "\n";
                            }
                            $aResult['contents'] .= " ------------------------------------------------------\n";
                            $adHasStats = false;
                            if ($adImpressions > 0) {
                                $adHasStats = true;
                                $aResult['contents'] .=  sprintf($adTextPrint, $strTotalImpressions) . ': ' .
                                                         sprintf('%15s', phpAds_formatNumber($adImpressions)) . "\n";
                                // Fetch the ad's impressions for the report period, grouped by day
                                $doDataSummaryAdHourly = OA_Dal::factoryDO('data_summary_ad_hourly');
                                $doDataSummaryAdHourly->selectAdd('SUM(impressions) as quantity');
                                $doDataSummaryAdHourly->selectAdd("DATE_FORMAT(day, '$date_format') as t_stamp_f");
                                $doDataSummaryAdHourly->ad_id = $aAd['bannerid'];
                                $doDataSummaryAdHourly->whereAdd('impressions > 0');
                                if (!is_null($oStartDate)) {
                                    $doDataSummaryAdHourly->whereAdd('day >= ' . $oDbh->quote($oStartDate->format('%Y-%m-%d'), 'timestamp'));
                                }
                                $doDataSummaryAdHourly->whereAdd('day <= ' . $oDbh->quote($oEndDate->format('%Y-%m-%d'), 'timestamp'));
                                $doDataSummaryAdHourly->groupBy('day');
                                $doDataSummaryAdHourly->orderBy('day DESC');
                                $doDataSummaryAdHourly->find();
                                if ($doDataSummaryAdHourly->getRowCount() > 0) {
                                    $total = 0;
                                    while ($doDataSummaryAdHourly->fetch()) {
                                        $aAdImpressions = $doDataSummaryAdHourly->toArray();
                                        $aResult['contents'] .= sprintf($adTextPrint, $aAdImpressions['t_stamp_f']) . ': ' .
                                                                sprintf('%15s', phpAds_formatNumber($aAdImpressions['quantity'])) . "\n";
                                        $total += $aAdImpressions['quantity'];
                                    }
                                    $aResult['contents'] .= sprintf($adTextPrint, $strTotalThisPeriod) . ': ' .
                                                            sprintf('%15s', phpAds_formatNumber($total)) . "\n";
                                } else {
                                    $aResult['contents'] .= '  ' . $strNoViewLoggedInInterval . "\n";
                                }
                            }
                            if ($adClicks > 0) {
                                $adHasStats = true;
                                $aResult['contents'] .= "\n" . sprintf($adTextPrint, $strTotalClicks) . ': ' .
                                                         sprintf('%15s', phpAds_formatNumber($adClicks)) . "\n";
                                // Fetch the ad's clicks for the report period, grouped by day
                                $doDataSummaryAdHourly = OA_Dal::factoryDO('data_summary_ad_hourly');
                                $doDataSummaryAdHourly->selectAdd('SUM(clicks) as quantity');
                                $doDataSummaryAdHourly->selectAdd("DATE_FORMAT(day, '$date_format') as t_stamp_f");
                                $doDataSummaryAdHourly->ad_id = $aAd['bannerid'];
                                $doDataSummaryAdHourly->whereAdd('clicks > 0');
                                if (!is_null($oStartDate)) {
                                    $doDataSummaryAdHourly->whereAdd('day >= ' . $oDbh->quote($oStartDate->format('%Y-%m-%d'), 'timestamp'));
                                }
                                $doDataSummaryAdHourly->whereAdd('day <= ' . $oDbh->quote($oEndDate->format('%Y-%m-%d'), 'timestamp'));
                                $doDataSummaryAdHourly->groupBy('day');
                                $doDataSummaryAdHourly->orderBy('day DESC');
                                $doDataSummaryAdHourly->find();
                                if ($doDataSummaryAdHourly->getRowCount() > 0) {
                                    $total = 0;
                                    while ($doDataSummaryAdHourly->fetch()) {
                                        $aAdClicks = $doDataSummaryAdHourly->toArray();
                                        $aResult['contents'] .= sprintf($adTextPrint, $aAdClicks['t_stamp_f']) . ': ' .
                                                                sprintf('%15s', phpAds_formatNumber($aAdClicks['quantity'])) . "\n";
                                        $total += $aAdClicks['quantity'];
                                    }
                                    $aResult['contents'] .= sprintf($adTextPrint, $strTotalThisPeriod) . ': ' .
                                                            sprintf('%15s', phpAds_formatNumber($total)) . "\n";
                                } else {
                                    $aResult['contents'] .= '  ' . $strNoClickLoggedInInterval . "\n";
                                }
                            }
                            if ($adConversions > 0) {
                                $adHasStats = true;
                                $aResult['contents'] .= "\n" . sprintf($adTextPrint, $strTotalConversions) . ': ' .
                                                         sprintf('%15s', phpAds_formatNumber($adConversions)) . "\n";
                                // Fetch the ad's conversions for the report period, grouped by day
                                $doDataSummaryAdHourly = OA_Dal::factoryDO('data_summary_ad_hourly');
                                $doDataSummaryAdHourly->selectAdd('SUM(conversions) as quantity');
                                $doDataSummaryAdHourly->selectAdd("DATE_FORMAT(day, '$date_format') as t_stamp_f");
                                $doDataSummaryAdHourly->ad_id = $aAd['bannerid'];
                                $doDataSummaryAdHourly->whereAdd('conversions > 0');
                                if (!is_null($oStartDate)) {
                                    $doDataSummaryAdHourly->whereAdd('day >= ' . $oDbh->quote($oStartDate->format('%Y-%m-%d'), 'timestamp'));
                                }
                                $doDataSummaryAdHourly->whereAdd('day <= ' . $oDbh->quote($oEndDate->format('%Y-%m-%d'), 'timestamp'));
                                $doDataSummaryAdHourly->groupBy('day');
                                $doDataSummaryAdHourly->orderBy('day DESC');
                                $doDataSummaryAdHourly->find();
                                if ($doDataSummaryAdHourly->getRowCount() > 0) {
                                    $total = 0;
                                    while ($doDataSummaryAdHourly->fetch()) {
                                        $aAdConversions = $doDataSummaryAdHourly->toArray();
                                        $aResult['contents'] .= sprintf($adTextPrint, $aAdConversions['t_stamp_f']) . ': ' .
                                                                sprintf('%15s', phpAds_formatNumber($aAdConversions['quantity'])) . "\n";
                                        $total += $aAdConversions['quantity'];
                                    }
                                    $aResult['contents'] .= sprintf($adTextPrint, $strTotalThisPeriod) . ': ' .
                                                            sprintf('%15s', phpAds_formatNumber($total)) . "\n";
                                } else {
                                    $aResult['contents'] .= '  ' . $strNoConversionLoggedInInterval . "\n";
                                }
                            }
                            $aResult['contents'] .= "\n";
                        }
                        if ($adHasStats == true) {
                            $adsWithDelivery = true;
                        }
                    }
                }
                if ($adsWithDelivery != true) {
                    $aResult['contents'] .= $strNoStatsForCampaign . "\n\n\n";
                }
            }
        }
        // Was anything found?
        if ($aResult['contents'] == '') {
            OA::debug('   - No placements with delivery for advertiser ID ' . $advertiserId . '.', PEAR_LOG_DEBUG);
            return false;
        }
        // Prepare the remaining email details
        $aResult['subject'] = $strMailSubject . ': ' . $aAdvertiser['clientname'];
        $body  = "$strMailHeader\n";
        $body .= "$strMailBannerStats\n";
        if (is_null($oStartDate)) {
            $body .= "$strMailReportPeriodAll\n\n";
        } else {
            $body .= "$strMailReportPeriod\n\n";
        }
        $body .= "{$aResult['contents']}\n";
        $body .= "$strMailFooter";
        $body  = str_replace("{clientname}",    $aAdvertiser['clientname'], $body);
        $body  = str_replace("{contact}",       $aAdvertiser['contact'], $body);
        $body  = str_replace("{adminfullname}", $aPref['admin_fullname'], $body);
        $body  = str_replace("{startdate}",     (is_null($oStartDate) ? '' : $oStartDate->format($date_format)), $body);
        $body  = str_replace("{enddate}",       $oEndDate->format($date_format), $body);
        $aResult['contents']  = $body;
        $aResult['userEmail'] = $aAdvertiser['email'];
        $aResult['userName']  = $aAdvertiser['contact'];
        return $aResult;
    }

    /**
     * A static method for preparing an advertiser's "impending expiry" report.
     *
     * @static
     * @param integer $advertiserId The advertiser's ID.
     * @param integer $placementId  The advertiser's ID.
     * @param string  $reason       The reason for expiration. One of:
     *                              "date", "impressions".
     * @param mixed   $value        The limit reason (ie. the date or value limit)
     *                              used to decide that the placement is about to
     *                              expire.
     * @return boolean|array False, if the report could not be created, otherwise
     *                       an array or arrays of four elements:
     *                          'subject'   => The email subject line.
     *                          'contents'  => The body of the email report.
     *                          'userEmail' => The email address to send the report to.
     *                          'userName'  => The real name of the email address, or null.
     */
    function prepareplacementImpendingExpiryEmail($advertiserId, $placementId, $reason, $value)
    {
        OA::debug('   - Preparing "impending expiry" report for advertiser ID ' . $advertiserId . '.', PEAR_LOG_DEBUG);

        $aPref = $GLOBALS['_MAX']['PREF'];
        if (is_null($aPref)) {
            $aPref = MAX_Admin_Preferences::loadPrefs();
        }

        Language_Default::load();
        global $strSirMadam, $strImpendingCampaignExpiry, $strMailHeader, $strMailFooter,
               $strImpendingCampaignExpiryDateBody, $strImpendingCampaignExpiryImpsBody,
               $strYourCampaign, $strTheCampiaignBelongingTo, $strImpendingCampaignExpiryBody,
               $strCampaign, $strBanner, $strLinkedTo, $strNoBanners;

        $oDbh =& OA_DB::singleton();

        // Prepare the result array
        $aResult = array();

        // Prepare the sub-result array
        $aSubResult = array(
            'subject'   => '',
            'contents'  => '',
            'userEmail' => '',
            'userName'  => null
        );

        // Prepare the expiration email body
        if ($reason == 'date') {
            $reason = $strImpendingCampaignExpiryDateBody;
        } else if ($reason == 'impressions') {
            $reason = $strImpendingCampaignExpiryImpsBody;
        } else {
            return false;
        }

        // Prepare the array of users that want warning emails
        $aUsers = array();
        if ($aPref['warn_admin'] == 't') {
            $aUsers['admin'] = 'admin';
        }
        if ($aPref['warn_agency'] == 't') {
            $aUsers['agency'] = 'agency';
        }
        if ($aPref['warn_client'] == 't') {
            $aUsers['advertiser'] = 'advertiser';
        }
        if (empty($aUsers)) {
            return false;
        }

        // Get the advertiser's details
        $doClients = OA_Dal::factoryDO('clients');
        $doClients->clientid = $advertiserId;
        $doClients->find();
        if (!$doClients->fetch()) {
            OA::debug('   - Error obtaining details for advertiser ID ' . $advertiserId . '.', PEAR_LOG_ERR);
            return false;
        }
        $aAdvertiser = $doClients->toArray();

        // Is warning the agency is set, is the agency ID the same as the admin ID?
        if (isset($aUsers['agency']) && $aAdvertiser['agencyid'] == 0) {
            OA::debug('   - Advertiser ID ' . $advertiserId . ' is owned by admin, no need to warn agency.', PEAR_LOG_ERR);
            $aUsers['admin'] = 'admin';
            unset($aUsers['agency']);
        }

        // Get & test the admin user's email address details, if required
        if (isset($aUsers['admin'])) {
            $doPreference = OA_Dal::factoryDO('preference');
            $doPreference->agencyid = 0;
            $doPreference->find();
            if (!$doPreference->fetch()) {
                unset($aUsers['admin']);
            } else {
                $aAdminOwner = $doPreference->toArray();
                if (empty($aAdminOwner['admin_email'])) {
                    OA::debug('   - No email for admin.', PEAR_LOG_ERR);
                    unset($aUsers['admin']);
                }
                if (empty($aAdminOwner['admin_fullname'])) {
                    $aAdminOwner['admin_fullname'] = $strSirMadam;
                }
            }
        }

        // Get & test the agency user's email address details, if required
        if (isset($aUsers['agency'])) {
            $doPreference = OA_Dal::factoryDO('preference');
            $doPreference->agencyid = $aAdvertiser['agencyid'];
            $doPreference->find();
            if (!$doPreference->fetch()) {
                unset($aUsers['agency']);
            } else {
                $aAgencyOwner = $doPreference->toArray();
                if (empty($aAgencyOwner['admin_email'])) {
                    OA::debug('   - No email for agency.', PEAR_LOG_ERR);
                    unset($aUsers['agency']);
                }
                if (empty($aAgencyOwner['admin_fullname'])) {
                    $aAgencyOwner['admin_fullname'] = $strSirMadam;
                }
            }
        }

        // Test the advertiser's email address details, if required
        if (isset($aUsers['advertiser'])) {
            if (empty($aAdvertiser['email'])) {
                OA::debug('   - No email for advertiser ID ' . $advertiserId . '.', PEAR_LOG_ERR);
                unset($aUsers['advertiser']);
            }
        }

        // Re-test that there is a user to report to, and
        // that they have an email address to mail
        if (empty($aUsers)) {
            return false;
        }

        // Double-check that the placement ID is owned by the advertiser
        $doCampaigns = OA_Dal::factoryDO('campaigns');
        $doCampaigns->campaignid = $placementId;
        $doCampaigns->find();
        if (!$doCampaigns->fetch()) {
            return false;
        }
        $aPlacement = $doCampaigns->toArray();
        if ($aPlacement['clientid'] != $advertiserId) {
            return false;
        }

        $aSubResult['subject'] = $strImpendingCampaignExpiry . ': ' . $aAdvertiser['clientname'];
        $body  = $strMailHeader . "\n\n";
        $body .= $reason . "\n\n";
        $body .= $strImpendingCampaignExpiryBody . "\n\n";
        $body .= $strCampaign . ' ' .
                 strip_tags(phpAds_buildName($aPlacement['campaignid'], $aPlacement['campaignname'])) . "\n";
        $body .= "-------------------------------------------------------\n\n";

        // Get the ads in the placement
        $doBanners = OA_Dal::factoryDO('banners');
        $doBanners->campaignid = $placementId;
        $doBanners->find();
        if ($doBanners->getRowCount() > 0) {
            // List the ads
            while ($doBanners->fetch()) {
                $aAd = $doBanners->toArray();
                $body .= ' ' . $strBanner . ' ' .
                         strip_tags(phpAds_buildBannerName($aAd['bannerid'], $aAd['description'], $aAd['alt'])) . "\n";
                if (!empty($aAd['url'])) {
                    $body .= '  ' . $strLinkedTo . ': ' . $aAd['url'] . "\n";
                }
                $body .= "\n";
            }
        } else {
            // No ads!
            $body .= ' ' . $strNoBanners . "\n\n";
        }

        $body .= "-------------------------------------------------------\n\n";
        $body .= "$strMailFooter";

        if (empty($aAdvertiser['clientname'])) {
        } else {
        }
        $body = str_replace("{date}",  $value, $body);
        $body = str_replace("{limit}", $value, $body);
        $body = str_replace("{adminfullname}", $aPref['admin_fullname'], $body);
        foreach ($aUsers as $user) {
            $realBody = $body;
            if ($user == 'admin') {
                $campaignReplace = $strTheCampiaignBelongingTo . ' ' . trim($aAdvertiser['clientname']);
                $realBody = str_replace("{clientname}", $campaignReplace, $realBody);
                $realBody = str_replace("{contact}", $aAdminOwner['admin_fullname'], $realBody);
            } else if ($user == 'agency') {
                $campaignReplace = $strTheCampiaignBelongingTo . ' ' . trim($aAdvertiser['clientname']);
                $realBody = str_replace("{clientname}", $campaignReplace, $realBody);
                $realBody = str_replace("{contact}", $aAgencyOwner['admin_fullname'], $realBody);
            } else if ($user == 'advertiser') {
                $campaignReplace = $strYourCampaign;
                $realBody = str_replace("{clientname}", $campaignReplace, $realBody);
                $realBody = str_replace("{contact}", $aAdvertiser['contact'], $realBody);
            }
            $aSubResult['contents'] = $realBody;
            if ($user == 'admin') {
                $aSubResult['userEmail'] = $aAdminOwner['admin_email'];
                $aSubResult['userName']  = $aAdminOwner['admin_fullname'];
            } else if ($user == 'agency') {
                $aSubResult['userEmail'] = $aAgencyOwner['admin_email'];
                $aSubResult['userName']  = $aAgencyOwner['admin_fullname'];
            } else if ($user == 'advertiser') {
                $aSubResult['userEmail'] = $aAdvertiser['email'];
                $aSubResult['userName']  = $aAdvertiser['contact'];
            }
            $aResult[] = $aSubResult;
        }
        return $aResult;
    }

    /**
     * A static method for premaring e-mails, advising of the activation of a
     * placement.
     *
     * @static
     * @param string $contactName   The name of the placement contact.
     * @param string $placementName The name of the deactivated placement.
     * @param array  $aAds          An array of ads in the placement, indexed by
     *                              ad_id, of an array containing the description,
     *                              alt description, and destination URL of the ad.
     * @return string The email that has been prepared.
     */
    function prepareActivatePlacementEmail($contactName, $placementName, $aAds)
    {
        $aPref = $GLOBALS['_MAX']['PREF'];
        if (is_null($aPref)) {
            $aPref = MAX_Admin_Preferences::loadPrefs();
        }

        $message  = "Dear $contactName,\n\n";
        $message .= 'The following ads have been activated because ' . "\n";
        $message .= 'the campaign activation date has been reached.';
        $message .= "\n\n";
        $message .= "-------------------------------------------------------\n";
        foreach ($aAds as $ad_id => $aData) {
            $message .= "Ad [ID $ad_id] ";
            if ($aData[0] != '') {
                $message .= $aData[0];
            } elseif ($aData[1] != '') {
                $message .= $aData[1];
            } else {
                $message .= 'Untitled';
            }
            $message .= "\n";
            $message .= "Linked to: {$aData[2]}\n";
            $message .= "-------------------------------------------------------\n";
        }
        $message .= "\nThank you for advertising with us.\n\n";
        $message .= "Regards,\n\n";
        $message .= $aPref['admin_fullname'];
        return $message;
    }

    /**
     * A static method for preparing e-mails, advising of the deactivation of a
     * placement.
     *
     * @static
     * @param string  $contactName   The name of the placement contact.
     * @param string  $placementName The name of the deactivated placement.
     * @param integer $reason        A binary flag field containting the reason(s) the
     *                               placement was deactivated:
     *                                   2  - No more impressions
     *                                   4  - No more clicks
     *                                   8  - No more conversions
     *                                   16 - Placement ended (due to date)
     * @param array   $aAds          An array of ads in the placement, indexed by
     *                               ad_id, of an array containing the description,
     *                               alt description, and destination URL of the ad.
     * @return string The email that has been prepared.
     */
    function prepareDeactivatePlacementEmail($contactName, $placementName, $reason, $aAds)
    {
        $aPref = $GLOBALS['_MAX']['PREF'];
        if (is_null($aPref)) {
            $aPref = MAX_Admin_Preferences::loadPrefs();
        }

        $message  = "Dear $contactName,\n\n";
        $message .= 'The following ads have been disabled because:' . "\n";
        if ($reason & MAX_PLACEMENT_DISABLED_IMPRESSIONS) {
            $message .= '  - There are no impressions remaining' . "\n";
        }
        if ($reason & MAX_PLACEMENT_DISABLED_CLICKS) {
            $message .= '  - There are no clicks remaining' . "\n";
        }
        if ($reason & MAX_PLACEMENT_DISABLED_CONVERSIONS) {
            $message .= '  - There are no conversions remaining' . "\n";
        }
        if ($reason & MAX_PLACEMENT_DISABLED_DATE) {
            $message .= '  - The campaign deactivation date has been reached' . "\n";
        }
        $message .= "\n";
        $message .= '-------------------------------------------------------' . "\n";
        foreach ($aAds as $ad_id => $aData) {
            $message .= "Ad [ID $ad_id] ";
            if ($aData[0] != '') {
                $message .= $aData[0];
            } elseif ($aData[1] != '') {
                $message .= $aData[1];
            } else {
                $message .= 'Untitled';
            }
            $message .= "\n";
            $message .= "Linked to: {$aData[2]}\n";
            $message .= '-------------------------------------------------------' . "\n";
        }
        $message .= "\n" . 'If you would like to continue advertising on our website,' . "\n";
        $message .= 'please feel free to contact us.' . "\n";
        $message .= 'We\'d be glad to hear from you.' . "\n\n";
        $message .= 'Regards,' . "\n\n";
        $message .= "{$aPref['admin_fullname']}";
        return $message;
    }

    /**
     * A static method to send an email.
     *
     * @static
     * @param string $subject   The subject line of the email.
     * @param string $contents  The body of the email.
     * @param string $userEmail The email address to send the report to.
     * @param string $userName  The readable name of the user. Optional.
     * @return boolean True if the mail was send, false otherwise.
     *
     * @copyright 2003-2007 Openads Limited
     * @copyright 2000-2003 The phpAdsNew developers
     */
    function sendMail($subject, $contents, $userEmail, $userName = null)
    {
        if (defined('DISABLE_ALL_EMAILS')) {
            return true;
        }

        $aPref = $GLOBALS['_MAX']['PREF'];
        if (is_null($aPref)) {
            $aPref = MAX_Admin_Preferences::loadPrefs();
        }

    	global $phpAds_CharSet;
    	// Build the "to:" header for the email
    	if (!get_cfg_var('SMTP')) {
    		$toParam = '"'.$userName.'" <'.$userEmail.'>';
    	} else {
    		$toParam = $userEmail;
    	}
    	// Build additional email headers
    	$headersParam = "Content-Transfer-Encoding: 8bit\r\n";
    	if (isset($phpAds_CharSet)) {
    		$headersParam .= "Content-Type: text/plain; charset=" . $phpAds_CharSet . "\r\n";
    	}
    	if (get_cfg_var('SMTP')) {
    		$headersParam .= 'To: "' . $userName . '" <' . $userEmail . ">\r\n";
    	}
    	$headersParam .= 'From: "' . $aPref['admin_fullname'] . '" <' . $aPref['admin_email'].'>' . "\r\n";
    	// Use only \n as header separator when qmail is used
    	if ($aPref['qmail_patch']) {
    		$headersParam = str_replace("\r", '', $headersParam);
    	}
    	// Add \r to linebreaks in the contents for MS Exchange compatibility
    	$contents = str_replace("\n", "\r\n", $contents);
    	return @mail($toParam, $subject, $contents, $headersParam);
    }

}

?>
