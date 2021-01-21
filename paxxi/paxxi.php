<?php
include_once (PHUB_DIR.'paxxi/paxxi-class.php');
$form = new paxxi('','');
$form->generate_hub_form();

if (isset($_POST['submit'])){
    echo ('start paxxi');
    $paxxi = new Paxxi($_POST['paxxi_document'], $_POST['paxxi_username']);
    $paxxi->get_order_from_priority();
}
