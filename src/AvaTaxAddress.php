<?php

namespace Drupal\commerce_avatax;

use AvaTax\Address;
use Drupal\address\AddressInterface;

class AvaTaxAddress {
  protected $address;

  public function __construct(AddressInterface $address) {
    $this->address = $address;
  }

  public function getAddress() {
    return new Address(
      $this->address->getAddressLine1(),
      $this->address->getAddressLine2(),
      null,
      $this->address->getAdministrativeArea(),
      $this->address->getLocality(),
      $this->address->getPostalCode(),
      $this->address->getCountryCode()
    );
  }
}
