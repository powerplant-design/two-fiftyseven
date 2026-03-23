<?php
/**
 * 257 Post Header Block — ACF block render template.
 *
 * Renders a centered page heading using the same archive-header classes
 * used at the top of archive/post index templates.
 *
 * ACF fields:
 *   post_header_title - heading text shown in h1
 *
 * @var array  $block      Block settings and attributes from ACF.
 * @var string $content    Rendered inner blocks HTML (unused).
 * @var bool   $is_preview True when rendering the block preview in the editor.
 * @var int    $post_id    The current post/page ID.
 */

$title = get_field( 'post_header_title' ) ?: __( 'Get in touch', 'two-fiftyseven' );
?>

<section class="post-header | block">
    <header class="post-index-header text-center">
        <h1 class="post-index-header__title"><?php echo esc_html( $title ); ?></h1>
    </header>
</section>
