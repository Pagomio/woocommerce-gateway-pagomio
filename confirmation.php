<?php
require_once '../../../wp-blog-header.php';
require_once './pagomio.php';

$pagomioGateway = new WC_Pagomio();
$pagomio = $pagomioGateway->getPagomioObject();
$request = $pagomio->getRequestPayment();
if($request) {
    $order = new WC_Order($request->reference);
    switch ($request->status) {
        case  \Pagomio\Pagomio::TRANSACTION_SUCCESS:
            $order->payment_complete();
            break;
        case  \Pagomio\Pagomio::TRANSACTION_ERROR:
            $order->update_status('failed', $request->message);
            break;
        case \Pagomio\Pagomio::TRANSACTION_PENDING:
            $order->update_status('pending', $request->message);
            break;
    }
}

?>