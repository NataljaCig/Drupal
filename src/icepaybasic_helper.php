<?php

/**
 *  ICEPAY Basic payment module for Commerce
 *  Helper for communication between Commerce and the ICEPAY API
 *
 *  @version 1.0.1
 *  @author Shahid Manzoor & Olaf Abbenhuis
 *  @copyright Copyright (c) 2012 ICEPAY B.V.
 *  www.icepay.com
 *
 */
include_once 'api/icepay_api_basic.php';

class drupalcommerceIcepayHelper {

    private $_result = "icepay/result";
    private $_postback = "icepay/postback";
    private $_version = "BASIC 1.4";
    private $_api = null;

    public function getVersions()
    {
        return sprintf(t("Version %s using PHP API 2 version %s"), $this->_version, $this->API()->getVersion());
    }

    public function API()
    {
        if ($this->_api == null)
            $this->_api = new Icepay_Project_Helper();
        return $this->_api;
    }

    public function getResultCode()
    {
        return $this->_result;
    }

    public function getPostbackCode()
    {
        return $this->_postback;
    }

    public function log($msg)
    {
        \Drupal::logger("icepaybasic")->notice($msg, []);
    }

    public function getResultURL()
    {
        global $base_url;
        return sprintf("%s/?q=%s", $base_url, $this->getResultCode());
    }

    public function getPostbackURL()
    {
        global $base_url;
        return sprintf("%s/?q=%s", $base_url, $this->getPostbackCode());
    }

    public function outputVersion($extended = false)
    {
        $dump = array(
            "module" => $this->getVersions(),
            "notice" => "Checksum validation passed!"
        );
        if ($extended) {
            $name = "commerce";
            $path = drupal_get_path('module', $name) . '/' . $name . '.info';
            $data = drupal_parse_info_file($path);
            $dump["additional"] = array(
                "drupal" => VERSION,
                "commerce" => $data["version"]
            );
        } else {
            $dump["notice"] = "Checksum failed! Merchant ID and Secret code probably incorrect.";
        }
        var_dump($dump);
        exit();
    }

    public function getDrupalTransactionStatus($icepayStatus = "OPEN")
    {
        switch ($icepayStatus) {
            case Icepay_StatusCode::SUCCESS: return COMMERCE_PAYMENT_STATUS_SUCCESS;
            case Icepay_StatusCode::OPEN: return COMMERCE_PAYMENT_STATUS_PENDING;
            case Icepay_StatusCode::ERROR: return COMMERCE_PAYMENT_STATUS_FAILURE;
            //case Icepay_StatusCode::REFUND: return COMMERCE_CREDIT_REFERENCE_CREDIT;
            //case Icepay_StatusCode::CHARGEBACK: return COMMERCE_CREDIT_REFERENCE_CREDIT;
        }
        return "";
    }

    public function getDrupalOrderStatus($icepayStatus = "OPEN")
    {
        switch ($icepayStatus) {
            case Icepay_StatusCode::SUCCESS: return "processing";
            case Icepay_StatusCode::OPEN: return "pending";
            case Icepay_StatusCode::ERROR: return "canceled";
            //case Icepay_StatusCode::REFUND: return "refund";
            //case Icepay_StatusCode::CHARGEBACK: return "refund";
        }
        return "";
    }

    public function getInfo()
    {
        return '<table class="form">
            <tr>
              <td><a onclick="window.open(\'http://www.icepay.com/webshop-modules/online-payments-for-drupalcommerce\');"><img src="' . file_create_url(drupal_get_path('module', 'commerce_icepay') . '/images/icepay-logo.png') . '" alt="ICEPAY" title="ICEPAY" border="0"/></a></td>
              <td><div style="float: left;">
                <div style="border: 1px solid #D1CBC9; padding: 0px 6px 0px 6px; margin:6px;">
                    <p>Official ICEPAY payment module</p>
                </div>
                <div style="border: 1px solid #D1CBC9; padding: 0px 6px 0px 6px; margin:6px;">
                    <p>ICEPAY has a <a onclick="window.open(\'http://www.currence.nl/nl-NL/OverOnzeProducten/LicentieEnCertificaathouders/Pages/iDEAL.aspx\');">Currence</a> (owner of iDEAL) iDEAL certification. This way, ICEPAY meets the Currence requirements of the competency guidelines;<BR/>ICEPAY has the Thawte SSL certification. Thus ICEPAY obtains the highest level of authentication during the processing of a payment. This means a guaranteed safe payment environment;<BR/>ICEPAY is an official business partner of Thuiswinkel.org (the Dutch organization for online shopping). This partnership shows that ICEPAY follows strict rules and regulations concerning privacy, right of withdrawal and dispute settlement.<BR/></p>
                    <p><img src="' . file_create_url(drupal_get_path('module', 'commerce_icepay') . '/images/logo-currence.png') . '" alt="Currence" title="Currence" border="0" style="margin-right:6px;"/><img src="' . file_create_url(drupal_get_path('module', 'commerce_icepay') . '/images/logo-thawte.png') . '" alt="Thawte" title="Thawte" border="0" style="margin-right:6px;"/><img src="' . file_create_url(drupal_get_path('module', 'commerce_icepay') . '/images/logo-thuiswinkel.png') . '" alt="Thuiswinkel certificate" title="Thuiswinkel certificate" border="0" style="margin-right:6px;"/></p>
                </div>
            </div></td>
            </tr>
			<tr>
              <td>Module Version</td>
              <td>' . $this->getVersions() . '</td>
            </tr>
            <tr>
              <td>Website</td>
              <td><a onclick="window.open(\'http://www.icepay.com/\');">www.icepay.com</a></td>
            </tr>
            <tr>
              <td>Support</td>
              <td><a onclick="window.open(\'http://www.icepay.com/downloads/pdf/manuals/drupal-commerce/drupal-commerce-manual-icepay.pdf\');">Manual</a></td>
            </tr>

            </table>';
    }

}