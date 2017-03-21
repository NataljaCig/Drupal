<?php

namespace Drupal\commerce_icepay\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Exception\InvalidRequestException;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_payment\PaymentMethodTypeManager;
use Drupal\commerce_payment\PaymentTypeManager;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayBase;
use Drupal\commerce_price\Price;
use Drupal\commerce_price\RounderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use GuzzleHttp\ClientInterface;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;

require_once(dirname(__FILE__) . '/../../../Api/icepay_api_basic.php');

/**
 * Provides the ICEPAY payment gateway.
 *
 * @CommercePaymentGateway(
 *   id = "icepay_checkout",
 *   label = @Translation("ICEPAY Payment Gateway"),
 *   display_label = @Translation("ICEPAY"),
 *    forms = {
 *     "offsite-payment" = "Drupal\commerce_icepay\PluginForm\IcepayCheckoutForm",
 *   },
 *   payment_method_types = {"icepay"},
 * )
 */
class IcepayCheckout extends OffsitePaymentGatewayBase implements IcepayCheckoutInterface
{

    /**
     * The HTTP client.
     *
     * @var \GuzzleHttp\Client
     */
    protected $httpClient;

    /**
     * The rounder.
     *
     * @var \Drupal\commerce_price\RounderInterface
     */
    protected $rounder;

    protected $paymentType = 'payment_icepay';

    /**
     * {@inheritdoc}
     */
    public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, PaymentTypeManager $payment_type_manager, PaymentMethodTypeManager $payment_method_type_manager, ClientInterface $client, RounderInterface $rounder)
    {
        parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $payment_type_manager, $payment_method_type_manager);
        $this->httpClient = $client;
        $this->rounder = $rounder;
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition)
    {
        return new static(
            $configuration,
            $plugin_id,
            $plugin_definition,
            $container->get('entity_type.manager'),
            $container->get('plugin.manager.commerce_payment_type'),
            $container->get('plugin.manager.commerce_payment_method_type'),
            $container->get('http_client'),
            $container->get('commerce_price.rounder')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function defaultConfiguration()
    {
        return [
            'icepay_merchant_id' => '',
            'icepay_secret_code' => '',
            'icepay_success_url' => '',
            'icepay_error_url' => '',
            'icepay_postback_url' => '',
        ] + parent::defaultConfiguration();
    }

    /**
     * {@inheritdoc}
     */
    public function buildConfigurationForm(array $form, FormStateInterface $form_state)
    {
        $form = parent::buildConfigurationForm($form, $form_state);

        $form['icepay_merchant_id'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Merchant ID'),
            '#default_value' => $this->configuration['icepay_merchant_id'],
            '#required' => TRUE,
        ];
        $form['icepay_secret_code'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Secret Code'),
            '#default_value' => $this->configuration['icepay_secret_code'],
            '#required' => TRUE,
        ];

        $form['icepay_success_url'] = [
            '#type' => 'textfield',
            '#attributes' => array('readonly' => 'readonly'),
            '#title' => $this->t('Thank you page'),
            '#default_value' => ''
        ];

        $form['icepay_error_url'] = [
            '#type' => 'textfield',
            '#attributes' => array('readonly' => 'readonly'),
            '#title' => $this->t('Error page'),
            '#default_value' => ''
        ];


        $form['icepay_postback_url'] = [
            '#type' => 'textfield',
            '#attributes' => array('readonly' => 'readonly'),
            '#title' => $this->t('Postback URL '),
            '#default_value' => $this->getNotifyUrl()->toString()
        ];

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function validateConfigurationForm(array &$form, FormStateInterface $form_state)
    {
        parent::validateConfigurationForm($form, $form_state);

        if (!$form_state->getErrors()) {
            $values = $form_state->getValue($form['#parents']);
            $this->configuration['icepay_merchant_id'] = $values['icepay_merchant_id'];
            $this->configuration['icepay_secret_code'] = $values['icepay_secret_code'];;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitConfigurationForm(array &$form, FormStateInterface $form_state)
    {
        parent::submitConfigurationForm($form, $form_state);
        if (!$form_state->getErrors()) {
            $values = $form_state->getValue($form['#parents']);
            $this->configuration['icepay_merchant_id'] = $values['icepay_merchant_id'];
            $this->configuration['icepay_secret_code'] = $values['icepay_secret_code'];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function onReturn(OrderInterface $order, Request $request)
    {
        $this->processIcepayResponse($order, $request);
    }


    /**
     * {@inheritdoc}
     */
    public function onCancel(OrderInterface $order, Request $request)
    {
        try {
            $this->processIcepayResponse($order, $request, true);
        } catch (PaymentGatewayException $e) {
            \Drupal::logger('commerce_payment')->error($e->getMessage());
            drupal_set_message(t('Payment failed at the payment server. Please review your information and try again.'), 'error');
        }

    }

    /**
     * {@inheritdoc}
     */
    public function onNotify(Request $request)
    {

        if ($request->getMethod() === 'GET') {
            echo "process url ok";
        } else if ($request->getMethod() === 'POST') {

            $icepay = new \Icepay_Postback();

            $configuration = $this->getConfiguration();
            $icepay->setMerchantID($configuration['icepay_merchant_id'])->setSecretCode($configuration['icepay_secret_code']);


            try {

                if ($icepay->validate()) {

                    $payment_storage = $this->entityTypeManager->getStorage('commerce_payment');
                    /** @var PaymentInterface[] $payments */
                    $icepay_payment = $payment_storage->load($icepay->getOrderID());
                    if (!isset($icepay_payment) || $icepay_payment->bundle() != 'payment_icepay') {
                        throw new PaymentGatewayException("ICEPAY payment not found");
                    }

                    $order = $icepay_payment->getOrder();
                    if ($order->id !== $icepay_payment->getReference || 0 !== $icepay_payment->getAmount()->compareTo($order->getTotalPrice())) {
                        throw new PaymentGatewayException("Error!"); //TODO
                    }

                    if (!$icepay_payment->getRemoteState() || ($icepay_payment->getRemoteState() !== $icepay->getStatus() && $icepay->canUpdateStatus($icepay_payment->getRemoteState(), $icepay->getStatus()))) {

                        $state = $icepay_payment->getState();
                        $stateValue = $state->getValue();
                        $currentStateName = end($stateValue);
                        $workflow = $state->getWorkflow();
                        $transition = $workflow->getTransition('cancel_new');

                        if ($currentStateName === 'icepay_new') {
                            switch ($icepay->getStatus()) {
                                case \Icepay_StatusCode::OPEN:
                                    $transition = $workflow->getTransition('set_open');
                                    break;
                                case \Icepay_StatusCode::SUCCESS:
                                    $transition = $workflow->getTransition('complete_new');
                                    break;
                                case \Icepay_StatusCode::ERROR:
                                    $transition = $workflow->getTransition('cancel_new');
                                    break;
                            }
                        } else if ($currentStateName === 'icepay_open') {
                            switch ($icepay->getStatus()) {
                                case \Icepay_StatusCode::SUCCESS:
                                    $transition = $workflow->getTransition('complete_new');
                                    break;
                                case \Icepay_StatusCode::ERROR:
                                    $transition = $workflow->getTransition('cancel_new');
                                    break;
                            }
                        } else throw new PaymentGatewayException("Refund and chagreback not supported");

                        $icepay_payment->getState()->applyTransition($transition);
                        $icepay_payment->setRemoteId($icepay->getOrderID());
                        $icepay_payment->setRemoteState($icepay->getStatus());
                        $icepay_payment->save();
                    }

                } else {
                    throw new PaymentGatewayException("Failed to validate payment geatway response");
                }

            } catch
            (Exception $e) {
                throw new PaymentGatewayException($e->getMessage(), $e->getCode(), $e);
            }
        }
    }

    private function processIcepayResponse(OrderInterface $order, Request $request, $isCanceled = false)
    {
        $icepay = new \Icepay_Result();

        $configuration = $this->getConfiguration();
        $icepay->setMerchantID($configuration['icepay_merchant_id'])->setSecretCode($configuration['icepay_secret_code']);

        try {

            if ($icepay->validate()) {

                $payment_storage = $this->entityTypeManager->getStorage('commerce_payment');
                $resultData = $icepay->getResultData();

                if ($order->id() !== $resultData->reference) {
                    throw new PaymentGatewayException("Wrong order ID");
                }

                /** @var PaymentInterface[] $payments */
                $payments = $payment_storage->loadByProperties(['order_id' => $order->id()]);
                if ($payments) {
                    foreach ($payments as $payment) {
                        if ($payment->id() == $resultData->orderID && 0 === $payment->getAmount()->compareTo($order->getTotalPrice())) {
                            $icepay_payment = $payment;
                        }
                    }
                }

                if (!isset($icepay_payment) || $icepay_payment->bundle() != 'payment_icepay') {
                    throw new PaymentGatewayException("ICEPAY payment not found");
                }

                if (!$icepay_payment->getRemoteState() || ($icepay_payment->getRemoteState() !== $icepay->getStatus() && $this->canUpdateIcepayStatus($order->getData('icepay_status'), $icepay->getStatus()))) {

                    $state = $icepay_payment->getState();
                    $stateValue = $state->getValue();
                    $currentStateName = end($stateValue);
                    $workflow = $state->getWorkflow();

                    if ($currentStateName === 'icepay_new') {
                        $transition = $workflow->getTransition('cancel_new');

                        switch ($icepay->getStatus()) {
                            case \Icepay_StatusCode::OPEN:
                                //Can happen in test mode if payment method does not allow "OPEN" state.
                                if (!$isCanceled) {
                                    $transition = $workflow->getTransition('set_open');
                                }
                                break;
                            case \Icepay_StatusCode::SUCCESS:
                                $transition = $workflow->getTransition('complete_new');
                                break;
                            case \Icepay_StatusCode::ERROR:
                                // Order canceled by the user
                                if ($resultData->statusCode === "Cancelled") {
                                    drupal_set_message($this->t('You have canceled checkout at @gateway but may resume the checkout process here when you are ready.', [
                                        '@gateway' => $this->getDisplayLabel(),
                                    ]));
                                    $icepay_payment->delete();
                                    return;
                                }
//                            $transition = $workflow->getTransition('cancel_new');
                                break;
                        }

                    } else if ($currentStateName === 'icepay_open') {
                        $transition = $workflow->getTransition('cancel_open');

                        switch ($icepay->getStatus()) {
                            case \Icepay_StatusCode::SUCCESS:
                                $transition = $workflow->getTransition('complete_open');
                                break;
                            case \Icepay_StatusCode::ERROR:
                                //$transition = $workflow->getTransition('cancel_open');
                                break;

                        }
                    } else throw new PaymentGatewayException("Invalid payment state");

                    if ($icepay->getStatus() == \Icepay_StatusCode::ERROR || $isCanceled) {
                        throw new PaymentGatewayException("Order " . $resultData->reference . " failed. " . $resultData->statusCode);
                    }

                    $icepay_payment->getState()->applyTransition($transition);
                    $icepay_payment->setRemoteId($icepay->getOrderID());
                    $icepay_payment->setRemoteState($icepay->getStatus());

                    $icepay_payment->save();
                }

            } else {
                throw new PaymentGatewayException("Failed to validate payment geatway response");
            }

        } catch (Exception $e) {
            throw new PaymentGatewayException($e->getMessage(), $e->getCode(), $e);
        }
    }

    private function canUpdateIcepayStatus($currentOrderIcepayStatus, $newOrderIcepayStatus)
    {
        switch ($newOrderIcepayStatus) {
            case \Icepay_StatusCode::SUCCESS:
                return ($currentOrderIcepayStatus == \Icepay_StatusCode::ERROR || $currentOrderIcepayStatus == \Icepay_StatusCode::AUTHORIZED || $currentOrderIcepayStatus == \Icepay_StatusCode::OPEN);
            case \Icepay_StatusCode::OPEN:
                return ($currentOrderIcepayStatus == \Icepay_StatusCode::OPEN);
            case \Icepay_StatusCode::AUTHORIZED:
                return ($currentOrderIcepayStatus == \Icepay_StatusCode::OPEN);
            case \Icepay_StatusCode::ERROR:
                return ($currentOrderIcepayStatus == \Icepay_StatusCode::OPEN || $currentOrderIcepayStatus == \Icepay_StatusCode::AUTHORIZED);
            default:
                return false;
        };
    }

    /**
     * {@inheritdoc}
     */
    public function setIcepayBasicModeCheckout(PaymentInterface $payment, array $extra)
    {

        $order = $payment->getOrder();
        $store = $order->getStore();


        $payment_storage = $this->entityTypeManager->getStorage('commerce_payment');

        $payment = $payment_storage->create([
            'type' => 'payment_icepay',
            'state' => 'icepay_new',
            'amount' => $payment->getAmount(),
            'payment_gateway' => $this->entityId,
            'payment_method' => 'icepay',
            'order_id' => $order->id(),
            'test' => $this->getMode() == 'test'
        ]);
        $payment->save();

        $amount = $this->rounder->round($payment->getAmount());
        $configuration = $this->getConfiguration();

        $language = \Drupal::languageManager()->getCurrentLanguage();
        $langcode = $language->getId();
        list($langcode) = explode('-', $langcode);

        /* Set the paymentObject */
        $paymentObj = new \Icepay_PaymentObject();

        $paymentObj
            ->setOrderID($payment->id())
            ->setAmount($amount->getNumber() * 100)
            ->setCountry($store->getAddress()->getCountryCode())
            ->setLanguage(strtoupper($langcode))
            ->setCurrency($amount->getCurrencyCode())
            ->setReference($order->id())
            ->setDescription($order->id());

        try {
            $basicmode = \Icepay_Basicmode::getInstance();
            $basicmode->setMerchantID($configuration['icepay_merchant_id'])
                ->setSecretCode($configuration['icepay_secret_code'])
                ->setSuccessURL($extra['return_url'])
                ->setErrorURL($extra['cancel_url'])
                ->validatePayment($paymentObj);

            return $basicmode->getURL();

        } catch (\Exception $e) {
            //TODO: error message
            drupal_set_message(t($e), 'error');
            return;
        }

    }

}
