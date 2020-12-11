<?php


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