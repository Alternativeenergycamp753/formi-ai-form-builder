<?php
    echo '</div>'; // Close the main container

    // Fetch footer pages
    $db = new SQLite3('database.sqlite');
    $result = $db->query('SELECT title, slug FROM pages WHERE show_in_footer = 1 ORDER BY title');
    $footer_pages = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $footer_pages[] = $row;
    }

    $footerText = $settings->get('footer_text');
    if (!empty($footer_pages) || !empty($footerText)) {
        echo '<footer class="footer mt-auto py-3 bg-light">
            <div class="container text-center">';
        
        // Display footer pages if any
        if (!empty($footer_pages)) {
            echo '<div class="footer-links mb-2">';
            foreach ($footer_pages as $page) {
                echo '<a href="/' . htmlspecialchars($page['slug']) . '" class="text-muted text-decoration-none mx-2">' 
                    . htmlspecialchars($page['title']) . '</a>';
            }
            echo '</div>';
        }

        // Display footer text if set
        if (!empty($footerText)) {
            echo '<div class="footer-text">' . $footerText . '</div>';
        }

        echo '</div>
        </footer>';
    }
    
    if ($footer_code = $settings->get('footer_code')) {
        echo $footer_code;
    }
?>
</body>
</html> 