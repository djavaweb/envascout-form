<?php
$purchase_detail = Envascout_Form::$envato_api->get_all_purchase_from_buyer(false);

if ( ! isset( $purchase_detail['purchases'] ) ) {
	echo 'API maintenance.';
	return;
}

$purchase_items = $purchase_detail['purchases'];

// Get purchases item and merge it become one.
// In case there is another purchase with the same item's name.
$items_bought = array();
foreach ( $purchase_items as $detail ) {
	$item_id = $detail['item']['id'];
	$item_name = $detail['item']['name'];
	$items_bought[ $item_id ] = $item_name;
}

$not_a_buyer_label = 'Please purchase our items.';
if ( ! empty( $field['config']['not_a_buyer'] ) ) {
	$not_a_buyer_label = $field['config']['not_a_buyer'];
}
?>

<?php echo $wrapper_before; ?>
	<?php echo $field_label; ?>
	<?php echo $field_before; ?>
	<select id="<?php echo esc_attr( $field_id ); ?>" data-field="<?php echo esc_attr( $field_base_id ); ?>" class="envascout-item-selector <?php echo esc_attr( $field_class ); ?>" name="<?php echo esc_attr( $field_name ); ?>" <?php echo $field_required; ?> <?php echo $field_structure['aria']; ?>>
		<?php if ( count( $items_bought ) === 0 ) : ?>
		<option value="<?php echo $not_a_buyer_label; ?>"><?php echo $not_a_buyer_label; ?></option>
		<?php else : ?>
		<?php foreach ( $items_bought as $item_id => $item_name ) : ?>
		<option value="<?php echo esc_attr( $item_id ); ?>"><?php echo $item_name; ?></option>
		<?php endforeach; ?>
		<?php endif; ?>
	</select>
	<?php echo $field_caption; ?>
	<?php echo $field_after; ?>
<?php echo $wrapper_after; ?>
