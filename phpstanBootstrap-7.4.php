<?php

require "./shops/7.4/vendor/autoload.php";
require "./shops/7.4/source/oxfunctions.php";



if (
    class_exists('\OxidEsales\Eshop\Application\Controller\OrderController')
    && !class_exists('Endereco\Oxid7Client\Controller\OrderController_parent')
) {
    class_alias(
        '\OxidEsales\Eshop\Application\Controller\OrderController',
        'Endereco\Oxid7Client\Controller\OrderController_parent'
    );
}

if (
    class_exists('OxidEsales\Eshop\Application\Component\UserComponent')
    && !class_exists('Endereco\Oxid7Client\Controller\UserComponent_parent')
) {
    class_alias(
        'OxidEsales\Eshop\Application\Component\UserComponent',
        'Endereco\Oxid7Client\Controller\UserComponent_parent'
    );
}

if (
    class_exists('OxidEsales\Eshop\Application\Model\User\UserShippingAddressUpdatableFields')
    && !class_exists('Endereco\Oxid7Client\Model\User\UserShippingAddressUpdatableFields_parent')
) {
    class_alias(
        'OxidEsales\Eshop\Application\Model\User\UserShippingAddressUpdatableFields',
        'Endereco\Oxid7Client\Model\User\UserShippingAddressUpdatableFields_parent'
    );
}

if (
    class_exists('OxidEsales\Eshop\Application\Model\User\UserUpdatableFields')
    && !class_exists('Endereco\Oxid7Client\Model\User\UserUpdatableFields_parent')
) {
    class_alias(
        'OxidEsales\Eshop\Application\Model\User\UserUpdatableFields',
        'Endereco\Oxid7Client\Model\User\UserUpdatableFields_parent'
    );
}

if (
    class_exists('OxidEsales\Eshop\Application\Controller\Admin\UserAddress')
    && !class_exists('Endereco\Oxid7Client\Controller\Admin\UserAddress_parent')
) {
    class_alias(
        'OxidEsales\Eshop\Application\Controller\Admin\UserAddress',
        'Endereco\Oxid7Client\Controller\Admin\UserAddress_parent'
    );
}

if (
    class_exists('OxidEsales\Eshop\Application\Controller\Admin\UserMain')
    && !class_exists('Endereco\Oxid7Client\Controller\Admin\UserMain_parent')
) {
    class_alias(
        'OxidEsales\Eshop\Application\Controller\Admin\UserMain',
        'Endereco\Oxid7Client\Controller\Admin\UserMain_parent'
    );
}

if (
    class_exists('OxidEsales\Eshop\Application\Model\Order')
    && !class_exists('Endereco\Oxid7Client\Model\Order_parent')
) {
    class_alias(
        'OxidEsales\Eshop\Application\Model\Order',
        'Endereco\Oxid7Client\Model\Order_parent'
    );
}
