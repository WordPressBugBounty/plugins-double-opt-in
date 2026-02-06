<?php
/**
 * @var string                                           $domain
 * @var array<forge12\contactform7\CF7DoubleOptIn\OptIn> $listOfOptIns
 * @var int                                              $numberOfPages
 * @var int                                              $currentPage
 * @var string                                           $slug
 */
?>
<form action="" method="get">
    <input type="hidden" name="page" value="<?php esc_attr_e( sanitize_text_field( $_GET['page'] ) ); ?>"/>
    <input type="hidden" name="pageNum" value="<?php esc_attr_e( $currentPage ); ?>"/>
	<?php if ( isset( $_GET['id'] ) ): ?>
        <input type="hidden" name="id" value="<?php esc_attr_e( (int) $_GET['id'] ); ?>"/>
	<?php endif; ?>
    <ul class="ui-table-filter">
		<?php do_action( 'f12_cf7_doubleoptin_ui_table_filter' ); ?>
    </ul>
</form>

<form action="" method="post">
    <table>
        <tr>
            <th></th>
            <th>
				<?php _e( 'ID', 'double-opt-in' ); ?>
            </th>
            <th>
				<?php _e( 'Hash', 'double-opt-in' ); ?>
            </th>
            <th>
				<?php _e( 'E-Mail', 'double-opt-in' ); ?>
            </th>
            <th>
				<?php _e( 'Category', 'double-opt-in' ); ?>
            </th>
            <th>
				<?php _e( 'Form ID', 'double-opt-in' ); ?>
            </th>
            <th>
				<?php _e( 'Confirmed ? ', 'double-opt-in' ); ?>
            </th>
            <th>
				<?php _e( 'Registration Date', 'double-opt-in' ); ?>
            </th>
        </tr>
		<?php foreach ( $listOfOptIns as $OptIn /** @var OptIn $OptIn */ ) : ?>
            <tr>
                <td>
                    <input type="checkbox" name="optin-id[]" value="<?php esc_attr_e( $OptIn->get_id() ); ?>"/>
                </td>
                <td>
                    <div class="f12-cf7-details" data-hash="<?php esc_attr_e( $OptIn->get_hash() ); ?>">
						<?php echo esc_html( $OptIn->get_id() ); ?>
                    </div>
                </td>
                <td>
                    <a href="javascript:void(0)" class="f12-cf7-details"
                       data-hash="<?php esc_attr_e( $OptIn->get_hash() ); ?>"
                       title="<?php _e( 'Show details', 'double-opt-in' ); ?>"><?php esc_attr_e( $OptIn->get_hash() ); ?></a>
                    <div class="on-hover">
                        <a href="<?php echo admin_url( 'admin.php?page=f12-cf7-doubleoptin_optin_view&hash=' . esc_attr( $OptIn->get_hash() ) ); ?>"><?php _e( 'Details', 'double-opt-in' ); ?></a>
                    </div>
                </td>
                <td>
                    <div class="f12-cf7-details">
						<?php echo esc_html( $OptIn->get_email() ); ?>
                    </div>
                </td>
                <td>
					<?php
					if ( 0 === $OptIn->get_category() ) {
						echo '---';
					} else {
						$Category = \forge12\contactform7\CF7DoubleOptIn\Category::get_by_id( $OptIn->get_category() );

						if ( null === $Category ) {
							echo '---';
						} else {
							$link = admin_url( 'admin.php' ) . '?page=f12-cf7-doubleoptin_categories_view&id=' . esc_attr( $Category->get_id() );
							echo '<a href="' . esc_url_raw( $link ) . '">' . esc_attr( $Category->get_name() ) . '</a>';
						}
					}
					?>
                </td>
                <td>
					<?php
					$form = '';

					$link = $OptIn->get_form_link();
					$name = $OptIn->get_form_name();

					if ( ! empty( $link ) ) {
						$form = sprintf( '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>', $link, $name );
					} else {
						$form = $name;
					}
					echo wp_kses( $form, array( 'a' => array( 'href' => array(), 'target' => '', 'rel' => '' ) ) );
					?>
                </td>
                <td>
					<?php if ( $OptIn->is_confirmed() ): ?>
                        <div class="dashicons dashicons-yes"></div>
					<?php else: ?>
                        <div class="dashicons dashicons-no"></div>
					<?php endif; ?>

					<?php ( $OptIn->is_confirmed() ) ? _e( 'Yes', 'double-opt-in' ) : _e( 'No', 'double-opt-in' ); ?>
                </td>
                <td>
					<?php
					/**
					 * Compatibility < 1.3.2
					 */
					echo esc_html( $OptIn->get_createtime( 'formatted' ) );
					?>
                </td>
            </tr>
		<?php endforeach; ?>
    </table>
    <ul class="ui-table-options">
		<?php do_action( 'f12_cf7_doubleoptin_ui_table_options' ); ?>
		<?php wp_nonce_field( 'f12_cf7_doubleoptin_ui_table_options_action', 'f12_cf7_doubleoptin_ui_table_options_nonce' ); ?>
    </ul>
</form>
<div class="tablenav-pages">
    <form action="<?php echo admin_url( 'admin.php' ); ?>" method="get">
    <span class="pagination-links">
	<?php
	$startPage = max( $currentPage - 5, 1 );
	$endPage   = min( $currentPage + 5, $numberOfPages );

	// if currentPage greater than 1, then show first and previous page links
	if ( $currentPage > 1 ): ?>
        <a class="first-page button"
           href="<?php echo esc_url( apply_filters( 'f12_cf7_doubleoptin_pagination_link', admin_url( 'admin.php' ), array(
			   'page'    => sanitize_text_field( $_GET['page'] ),
			   'pageNum' => 1
		   ) ) ); ?>">
            <span class="screen-reader-text"><?php _e( 'First Page', 'double-opt-in' ); ?></span><span
                    aria-hidden="true">«</span>
        </a>
        <a class="previous-page button"
           href="<?php echo esc_url( apply_filters( 'f12_cf7_doubleoptin_pagination_link', admin_url( 'admin.php' ), array(
			   'page'    => sanitize_text_field( $_GET['page'] ),
			   'pageNum' => ( $currentPage - 1 )
		   ) ) ); ?>">
            <span class="screen-reader-text"><?php _e( 'Previous Page', 'double-opt-in' ); ?></span><span
                    aria-hidden="true">‹</span>
        </a>
	<?php else: ?>
        <span class="tablenav-pages-navspan button disabled" aria-hidden="true">«</span>
        <span class="tablenav-pages-navspan button disabled" aria-hidden="true">‹</span>
	<?php endif;

	// form for current page input
	?>
        <span class="paging-input">
        <input type="hidden" name="page" value="<?php echo esc_attr( sanitize_text_field( $_GET['page'] ) ); ?>">
        <input name="pageNum" class="current-page" type="number" min="1" max="<?php echo esc_attr( (int) $numberOfPages ); ?>"
               value="<?php echo esc_attr( (int) $currentPage ); ?>" onchange="this.form.submit()">
            <span class="tablenav-paging-text"> <?php _e( 'of' ); ?><span
                        class="total-pages"> / <?php echo esc_html( (int) $numberOfPages ); ?></span></span>
            </span>
	<?php

	// if currentPage less than numberOfPages, then show next and last page links
	if ( $currentPage < $numberOfPages ): ?>
        <a class="next-page button"
           href="<?php echo esc_url( apply_filters( 'f12_cf7_doubleoptin_pagination_link', admin_url( 'admin.php' ), array(
			   'page'    => sanitize_text_field( $_GET['page'] ),
			   'pageNum' => ( $currentPage + 1 )
		   ) ) ); ?>">
            <span class="screen-reader-text"><?php _e( 'Next Page', 'double-opt-in' ); ?></span><span
                    aria-hidden="true">›</span>
        </a>
        <a class="last-page button"
           href="<?php echo esc_url( apply_filters( 'f12_cf7_doubleoptin_pagination_link', admin_url( 'admin.php' ), array(
			   'page'    => sanitize_text_field( $_GET['page'] ),
			   'pageNum' => $numberOfPages
		   ) ) ); ?>">
            <span class="screen-reader-text"><?php _e( 'Last Page', 'double-opt-in' ); ?></span><span
                    aria-hidden="true">»</span>
        </a>
	<?php else: ?>
        <span class="tablenav-pages-navspan button disabled" aria-hidden="true">›</span>
        <span class="tablenav-pages-navspan button disabled" aria-hidden="true">»</span>
	<?php endif; ?>
        </span>

    </form>
</div>