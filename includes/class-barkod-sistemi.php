<?php
declare(strict_types=1);
if (!defined('ABSPATH')) {
    exit;
}

class Barkod_Sistemi{

    private $admin;
    public function __construct() {
        $this->admin = new Barkod_Sistemi_Admin();
    }

    public function init(){
        $this->admin->init();
    }
}