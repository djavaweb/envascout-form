<?php
$tinymce_args = array(
	'textarea_name' => esc_attr( $field_base_id ),
	'textarea_rows' => 4,
);
?>
<?php echo $wrapper_before; ?>
	<?php echo $field_label; ?>
	<?php echo $field_before; ?>
		<?php wp_editor( esc_html( $field_value ), esc_attr( $field_base_id ), $tinymce_args ); ?>
		<?php echo $field_caption; ?>
	<?php echo $field_after; ?>
<?php
echo $wrapper_after;
