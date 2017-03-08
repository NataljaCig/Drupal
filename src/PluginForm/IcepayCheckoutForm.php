<?php

namespace Drupal\commerce_icepay\PluginForm;

use Drupal\commerce_payment\PluginForm\PaymentOffsiteForm as BasePaymentOffsiteForm;
use Drupal\Core\Form\FormStateInterface;

class IcepayCheckoutForm extends BasePaymentOffsiteForm {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    /** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment */
    $payment = $this->entity;
    /** @var \Drupal\commerce_icepay\Plugin\Commerce\PaymentGateway\IcepayCheckoutInterface $payment_gateway_plugin */
    $payment_gateway_plugin = $payment->getPaymentGateway()->getPlugin();

    $extra = [
      'return_url' => $form['#return_url'],
      'cancel_url' => $form['#cancel_url'],
    ];

    $redirect_url = $payment_gateway_plugin->setIcepayBasicModeCheckout($payment, $extra);

    if (!$redirect_url) {
      return [
        '#type' => 'inline_template',
        '#template' => "<span>{{ 'There was an error bringing you to ICEPAY.'|t }}</span>",
      ];
    }

    $data = [
      'return' => $form['#return_url'],
      'cancel' => $form['#cancel_url'],
      'total' => $payment->getAmount()->getNumber(),
    ];

    return $this->buildRedirectForm($form, $form_state, $redirect_url, $data, 'get');
  }

}
