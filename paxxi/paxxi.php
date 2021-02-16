<?php
include_once (PHUB_DIR.'paxxi/paxxi-class.php');
$form = new Paxxi('','');
$form->generate_hub_form();

if (isset($_POST['submit']) && !empty($_POST['paxxi_username'])){
    echo ('start paxxi');
    $paxxi = new Paxxi($_POST['paxxi_document'], $_POST['paxxi_username']);
    $paxxi_orders = $paxxi->get_order_from_priority();
    foreach ($paxxi_orders as $order){
        // if valid update  Priority
            $paxxi->update_priority_order($order);
        // if error add to error stack
    }
}
