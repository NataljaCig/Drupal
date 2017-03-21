<?php

namespace Drupal\commerce_icepay\Plugin\Commerce\PaymentType;

use Drupal\commerce_payment\Plugin\Commerce\PaymentType\PaymentDefault;

/**
 * Provides the default payment type.
 *
 * @CommercePaymentType(
 *   id = "payment_icepay",
 *   label = @Translation("ICEPAY"),
 *   workflow = "payment_icepay",
 * )
 */
class Icepay extends PaymentDefault  {
    

}
