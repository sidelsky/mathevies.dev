<?php include("header.php"); ?>

	<section role="main">

		<?php if (have_posts()): while (have_posts()) : the_post(); ?>

			<header>
				<h1><?php the_title(); ?></h1>
			</header>

			<?php the_content(); ?>

		<?php endwhile; endif; ?>

	</section>

<?php include("footer.php"); ?>
