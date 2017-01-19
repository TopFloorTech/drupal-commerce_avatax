<?php

namespace Drupal\commerce_avatax;

use AvaTax\Line;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\commerce_shipping\Plugin\Field\FieldType\ShipmentItem;

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
    $line = new Line();
    $line
      ->setNo(count($this->lines) + 1)
      ->setItemCode($orderItem->getPurchasedEntity()->get('sku')->value)
      ->setDescription($orderItem->getTitle())
      ->setQty($orderItem->getQuantity())
      ->setAmount($orderItem->getTotalPrice());

    $this->lines[] = $line;
  }

  public function addFreight(ShipmentInterface $shipment) {
    $line = new Line();
    $line
      ->setNo(count($this->lines) + 1)
      ->setItemCode($shipment->getShippingMethodId())
      ->setDescription($shipment->getShippingMethod()->getName())
      ->setQty(1)
      ->setAmount($shipment->getAmount())
      ->setTaxCode("FR");

    $this->lines[] = $line;
  }

  public function getLines() {
    return $this->lines;
  }
}
