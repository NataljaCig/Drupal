<?php

namespace Drupal\commerce_icepay\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayInterface;


/**
 * Provides the interface for the ICEPAY payment gateway.
 */
interface IcepayCheckoutInterface extends OffsitePaymentGatewayInterface {


  /**
   * Start ICEPAY Basic Mode Checkout
   *
   * @param \Drupal\commerce_payment\Entity\PaymentInterface $payment
   *   The payment.
   * @param array $extra
   *   Extra data needed for this request, ex.: cancel url, return url, transaction mode, etc....
   *
   * @return Url
   *   Redirect URL.
   *
   */
  public function setIcepayBasicModeCheckout(PaymentInterface $payment, array $extra);
  
}
