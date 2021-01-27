<?php

// Add to WP dashboard - May require changing screen options (top right on dashboard)
add_action("wp_dashboard_setup", "ssfwc_add_dashboard_widget");

function ssfwc_add_dashboard_widget()
{
  /**
   * Setup dashboard widget
   */
  wp_add_dashboard_widget(
    "ssfwc_widget",
    "Stock Syncer for Woocommerce",
    "ssfwc_widget_callback"
  );

  global $wp_meta_boxes;
  $normal_dash = $wp_meta_boxes["dashboard"]["normal"]["core"];
  $custom_dash = [
    "ssfwc_widget" => $normal_dash["ssfwc_widget"],
  ];
  unset($normal_dash["ssfwc_widget"]);
  $sorted_dash = array_merge($custom_dash, $normal_dash);
  $wp_meta_boxes["dashboard"]["normal"]["core"] = $sorted_dash;
}

function ssfwc_widget_callback()
{
  ?>
    <style>
        .loader {
        border: 8px solid #f3f3f3; /* Light grey */
        border-top: 8px solid #3498db; /* Blue */
        border-radius: 50%;
        width: 40px;
        height: 40px;
        animation: spin 2s linear infinite;
        text-align: center;
        margin: 0 auto;
        }

        @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
        }
    </style>

    <p><button class="ssfwc ssfwc_run_portwest">Run Portwest</button></p>
    <p><button class="ssfwc ssfwc_run_helly_hansen">Run Helly Hansen</button></p>
    <hr/>
    <div id="ssfwc_results" style="max-height: 300px; overflow: auto;"></div>

    <script>

        const buttons = document.getElementsByClassName("ssfwc");
        const results = document.getElementById("ssfwc_results");

        for (let button of buttons) {
            button.addEventListener("click", () => {
                results.innerHTML = '<div class="loader"></div><p style="text-align: center">Just a moment sir...</p>';
                
                jQuery.post(
                    ajaxurl, 
                    { action: button.classList[1] },
                    (response) => {
                        results.innerText = response
                    }
                )
            });
        }

    </script>
    <?php
}
