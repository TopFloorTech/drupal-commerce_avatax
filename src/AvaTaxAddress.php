<?php

namespace Drupal\commerce_avatax;

use Drupal\address\AddressInterface;

class AvaTaxAddress {
  protected $address;

  public function __construct(AddressInterface $address) {
    $this->address = $address;
  }

  public function getAddress() {
    return [
      'line1' => $this->address->getAddressLine1(),
      'line2' => $this->address->getAddressLine2(),
      'city' => $this->address->getAdministrativeArea(),
      'region' => $this->address->getLocality(),
      'postalCode' => $this->address->getPostalCode(),
      'country' => $this->address->getCountryCode()
    ];
  }
}
