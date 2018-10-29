<?php
namespace Twee\TwoCheckout;

final class ApiLegacy
{
    private $vendorCode = '';
    private $secretCode = '';

    public function __construct(string $vendorCode, string $secretCode)
    {
        $this->vendorCode = $vendorCode;
        $this->secretCode = $secretCode;
    }
}
