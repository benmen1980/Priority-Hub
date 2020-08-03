<?php

// Konimbo options
echo ('<br><br>');

?>
<div>
    <div class="wrap woocommerce">
    <form action="" method="post">
        <input type="hidden" name="action" value="sync_konimbo">
        <table class="form-table">

            <tbody>
            <tr valign="top">
                <th scope="row" class="titledesc">
                    <label for="order">Konimbo Order <span class="woocommerce-help-tip"></span></label>
                </th>
                <td class="forminp forminp-text">
                    <input name="order" id="konimbo-order" type="text" style="" value="" class="" placeholder="">
                </td>
            </tr>
            <tr valign="top">
                <th scope="row" class="titledesc">
                    <label for="last-sync-date">Last Sync Date<span class="woocommerce-help-tip"></span></label>
                </th>
                <td class="forminp forminp-text">
                    <p><?php $user = wp_get_current_user();
                    $user_meta = get_user_meta( $user->ID);
                    echo get_user_meta( $user->ID, 'konimbo_last_sync_time', true );?></p>
                </td>
            </tr>



            </tbody>
        </table>
        <input name="submit" type="submit" value="Post Order" />
    </form>
    </div>
		<?php
		wp_nonce_field( 'acme-settings-save', 'acme-custom-message' );


if ( isset( $_POST['submit'] ) ) {
    // fetch data
	$user = wp_get_current_user();
	Konimbo::instance()->order = $_POST['order'];
	Konimbo::instance()->debug = true;
	Konimbo::instance()->generalpart = '';
	// procees
	$orders = Konimbo::instance()->get_orders_by_user( $user );
	$responses[$user->ID] = Konimbo::instance()->process_orders($orders,$user);
	$messages =  Konimbo::instance()->processResponse($responses);
	$message = $messages[$user->ID];
	$emails  = [ $user->user_email ];
	$subject = 'Priority Konimbo API error ';
	if (false == $message['is_error']) {
		Konimbo::instance()->sendEmailError($subject, $message);
	}
	echo $message['message'].'<br>';
}
?>
</div>



<?php

kriesi_pagination();

function kriesi_pagination($pages = '', $range = 2) {
	$paged = get_query_var( 'paged' ) ? get_query_var( 'paged' ) : 1; // setup pagination

	$the_query = new WP_Query( array(
			'post_type'      => 'konimbo_order',
			'author'         => get_current_user_id(),
			'paged'          => $paged,
			//'post_status'    => 'publish',
			'orderby'        => 'date',
			'order'          => 'DESC',
			'posts_per_page' => 10
		)
	);
	?>
	<table class="responstable">
            <thead>
                <tr>
                    <th></th>
                    <th>Name and Konimbo Order</th>
                    <th>Priority Order</th>
                    <th>Posting date</th>

                </tr>
            </thead>
            <tbody>
                <tr>
                    <?php while ( $the_query->have_posts() ) : $the_query->the_post();
                    $id = get_the_ID();
                    $post_tags = wp_get_post_tags($id);
                    $ordername = '';
                    if(isset($post_tags[0]->name)) {
	                    $ordername = $post_tags[0]->name;
                    }
                    ?>
                    <td><input type="checkbox"/></td>
                    <td><?php echo the_title(); ?></td>
                    <td><?php echo $ordername; ?></td>
                    <td><?php echo get_the_date('c') ?></td>
                </tr>

    <?php endwhile; ?>
     </tbody>
        </table>
<?php

	echo '<nav>';
	echo '<div class="nav-previous alignleft">' . get_next_posts_link( 'Prev Orders', $the_query->max_num_pages ) . '</div>'; //Older Link using max_num_pages
	echo '<div class="nav-next alignright">' . get_previous_posts_link( 'Next Orders', $the_query->max_num_pages ) . '</div>'; //Newer Link using max_num_pages
	echo "</nav>";


	wp_reset_postdata(); // Rest Data
}
