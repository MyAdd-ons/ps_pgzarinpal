<?php

/**
* 2007-2020 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2020 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/
class PgzarinpalModel extends ObjectModel
{
    public $id_pgzarinpal;

    public $cart_id;

    public $authority;

    public $date_add;

    /**
     * @see ObjectModel::$definition
     */
    public static $definition = [
        'table'   => 'pgzarinpal',
        'primary' => 'id_pgzarinpal',
        'fields'  => [
            'id_pgzarinpal' => ['type' => self::TYPE_INT],
            'cart_id'       => ['type' => self::TYPE_INT],
            'authority'     => ['type' => self::TYPE_STRING],
            'date_add'      => ['type' => self::TYPE_DATE],
        ],
    ];

    public static function getIdByAuthority($authority)
    {
        return Db::getInstance()->getValue("SELECT `id_pgzarinpal` FROM `" . _DB_PREFIX_ . self::$definition['table'] . "` WHERE `authority` = '" . $authority . "'");
    }

    public static function getIdByCartId($cart_id)
    {
        return Db::getInstance()->getValue("SELECT `id_pgzarinpal` FROM `" . _DB_PREFIX_ . self::$definition['table'] . "` WHERE `cart_id` = '" . $cart_id . "'");
    }

    public static function cartIdExists($cart_id)
    {
        $res = Db::getInstance()->getRow("SELECT `id_pgzarinpal` FROM `" . _DB_PREFIX_ . self::$definition['table'] . "` WHERE `cart_id` = '" . $cart_id . "'");
        $row_count = Db::getInstance()->numRows();
        return $row_count > 0 ? true : false;
    }
}
