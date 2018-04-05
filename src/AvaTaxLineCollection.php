<?php

namespace Drupal\commerce_avatax;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\commerce_shipping\Plugin\Field\FieldType\ShipmentItem;
use Drupal\Core\Entity\EntityInterface;

class AvaTaxLineCollection {
  protected $lines = [];

  /**
   * AvaTaxLineCollection constructor.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   * @param bool $includeShipping
   */
  public function __construct(OrderInterface $order, $includeShipping = FALSE) {
    foreach ($order->getItems() as $orderItem) {
      $this->addLine($orderItem);
    }

    if ($includeShipping && $order->hasField('shipments') && !$order->get('shipments')->isEmpty()) {
      $shipments = $order->get('shipments');

      /** @var ShipmentItem $shipmentItem */
      foreach ($shipments as $shipmentItem) {
        /** @var ShipmentInterface $shipment */
        $shipment = $shipmentItem->entity;

        $this->addFreight($shipment);
      }
    }
  }

  public function addLine(OrderItemInterface $orderItem) {
    $purchasedEntity = $orderItem->getPurchasedEntity();

    if ($purchasedEntity instanceof EntityInterface) {
      $line = [
        'number' => count($this->lines) + 1,
        'quantity' => $orderItem->getQuantity(),
        'amount' => $orderItem->getTotalPrice()->getNumber(),
        'itemCode' => $orderItem->getPurchasedEntity()->get('sku')->value,
        'description' => $orderItem->getTitle()
      ];

      $this->lines[] = $line;
    }
  }

  public function addFreight(ShipmentInterface $shipment) {
    $amount = $shipment->getAmount();

    if (empty($amount)) {
      return;
    }

    $line = [
      'number' => count($this->lines) + 1,
      'quantity' => 1,
      'amount' => $amount->getNumber(),
      'itemCode' => $shipment->getShippingMethodId(),
      'description' => $shipment->getShippingMethod()->getName(),
      'taxCode' => 'FR020200',
    ];

    $this->lines[] = $line;
  }

  public function getLines() {
    return $this->lines;
  }
}
