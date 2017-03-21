<?php

namespace Drupal\commerce_icepay\Plugin\Commerce\PaymentMethodType;

use Drupal\commerce_payment\Entity\PaymentMethodInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentMethodType\PaymentMethodTypeBase;

/**
 * Provides the ICEPAY payment method type.
 *
 * @CommercePaymentMethodType(
 *   id = "icepay",
 *   label = @Translation("ICEPAY"),
 *   create_label = @Translation("ICEPAY"),
 * )
 */
class Icepay extends PaymentMethodTypeBase  {

  /**
   * {@inheritdoc}
   */
  public function buildLabel(PaymentMethodInterface $payment_method) {
    return 'ICEPAY';
  }

}
