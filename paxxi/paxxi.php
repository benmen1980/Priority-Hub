<?php
include_once (PHUB_DIR.'paxxi/paxxi-class.php');
$form = new Paxxi('','');
$form->generate_hub_form();

if (isset($_POST['submit']) && !empty($_POST['paxxi_username'])){
    echo ('start paxxi');
    $paxxi = new Paxxi($_POST['paxxi_document'], $_POST['paxxi_username']);
    $paxxi->get_order_from_priority();
}
