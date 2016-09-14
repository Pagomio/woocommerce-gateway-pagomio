<?php
require_once '../../../wp-blog-header.php';
require_once './pagomio.php';

$pagomioGateway = new WC_Pagomio();
$pagomio = $pagomioGateway->getPagomioObject();
try{
	$request = $pagomio->getRequestPayment();
}catch (Exception $e){
	$request = null;
}
get_header('shop');
if($request){
	$referece = $request->reference;
	$status = $request->status;
	$transaction_id =  $request->transaction_id;
	$total_amount = $request->total_amount;
	$tax_amount = $request->tax_amount;
	$user_email = $request->user_email;
	$user_names = $request->user_names;
	$user_lastnames = $request->user_lastnames;
	switch ($request->status) {
		case  \Pagomio\Pagomio::TRANSACTION_SUCCESS:
			$message = 'Transacción Aprobada';
			break;
		case  \Pagomio\Pagomio::TRANSACTION_ERROR:
			$message = 'Transacción Rechazada';
			break;
		case \Pagomio\Pagomio::TRANSACTION_PENDING:
			$message = 'Transacción Pendiente';
			break;
		default:
			$message = '-';
			break;
	}
?>
	<div style="text-align: center;margin-top: 50px;">
		<table style="margin: auto;width: 100%;max-width: 500px;" >
			<tr align="center">
				<th colspan="2">DATOS DEL PAGO</th>
			</tr>
			<tr align="right">
				<th>Estado</th>
				<td><?php echo $message; ?></td>
			</tr>
			<tr align="right">
				<th>Transacción ID</th>
				<td><?php echo $transaction_id; ?></td>
			</tr>
			<tr align="right">
				<th>Pedido</th>
				<td><?php echo $referece; ?></td>
			</tr>
			<tr align="right">
				<th>Valor total</th>
				<td>$ <?php echo number_format($total_amount); ?> </td>
			</tr>
			<tr align="right">
				<th>Usuario</th>
				<td><?php echo $user_names . ' ' . $user_lastnames; ?> </td>
			</tr>
		</table>
		<?php echo $pagomioGateway->get_icon(); ?>
	</div>
<?php
} else {
	echo '<h1>Error, transacción inválida.</h1>';
}
get_footer('shop');
?>