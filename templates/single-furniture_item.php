<?php
get_header(); 

if ( have_posts() ) : 
    while ( have_posts() ) : the_post(); ?>
        
        <div class="furniture-item-detail">
            <h1><?php the_title(); ?></h1>
            <div class="furniture-image">
                <?php the_post_thumbnail(); ?>
            </div>
            <div class="furniture-description">
                <?php the_content(); ?>
            </div>
            <div class="furniture-price-history">
                <h2>Price History</h2>
                <canvas id="price-history-chart"></canvas>
            </div>
        </div>

        <script>
            // Use Chart.js or another graph library to render price history graph
        </script>

    <?php endwhile; 
endif; 

get_footer(); 
