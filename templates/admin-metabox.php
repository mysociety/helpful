<table class="form-table meta_box">
    <tbody>
        <tr>
            <th style="width:20%"><label><?php echo esc_html_x('Pro', 'meta box label', 'helpful'); ?></label></th>
            <td><?php echo $pro; ?> <?php printf("(%s%%)", $pro_percent); ?></td>
        </tr>
        <tr>
            <th style="width:20%"><label><?php echo esc_html_x('Contra', 'meta box label', 'helpful'); ?></label></th>
            <td><?php echo $contra; ?> <?php printf("(%s%%)", $contra_percent); ?></td>
        </tr>
        <tr>
            <th style="width:20%">
                <label for="helpful_remove_data"><?php echo esc_html_x('Reset Post', 'meta box label', 'helpful'); ?></label>
            </th>
            <td>
                <input type="checkbox" name="helpful_remove_data" id="helpful_remove_data" value="yes">
                <label for="helpful_remove_data">
                    <span class="description"><?php echo esc_html_x('Select to reset the entries of Helpful for this post.', 'checkbox label', 'helpful'); ?></span>
                </label>
            </td>
        </tr>
        <tr>
            <th style="width:20%">
                <label for="helpful_hide_on_post"><?php echo esc_html_x('Hide Helpful', 'meta box label', 'helpful'); ?></label>
            </th>
            <td>
                <input type="checkbox" name="helpful_hide_on_post" id="helpful_hide_on_post" value="yes" <?php checked( $hide, 'on' ); ?>>
                <label for="helpful_hide_on_post">
                    <span class="description"><?php echo esc_html_x('Select to hide Helpful in this post.', 'checkbox label', 'helpful'); ?></span>
                </label>
            </td>
        </tr>
    </tbody>
</table>