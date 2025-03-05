# **Using the Nav Menu Field**

## **Basic Setup**

To get started with the **Nav Menu Field** in SCF, follow these steps to configure and display a WordPress navigation menu:

### **Step 1: Create a New Field Group**

First, create a new field group in **Advanced Custom Fields (SCF)**. This field group will hold your custom fields, including the **Nav Menu** field.

1. Go to **Custom Fields > Add New** in your WordPress admin panel.
2. Title your field group and choose the location rules for where this field group should be displayed (e.g., on a specific page or post type).

### **Step 2: Add a Nav Menu Field**

Once you've created a new field group, add a new field of type **Nav Menu**. This field will allow users to select a navigation menu from the available menus on your site.

1. Click **Add Field** within the field group.
2. Set the **Field Type** to **Nav Menu**.
3. Configure the necessary field options based on your needs.

### **Step 3: Configure Options**

Configure the field options for the **Nav Menu** field to tailor it to your needs.

- **Return Value:** Choose how you want the selected menu to be returned:
  - **Menu ID:** The ID of the selected menu.
  - **Nav Menu HTML:** The raw HTML of the selected menu.
  - **Nav Menu Object:** The complete menu object, including properties like the menu name and term ID.

- **Menu Container:** This option determines the wrapper tag for the menu when the **Nav Menu HTML** return format is selected. Common options include `div`, `nav`, or `none`. You can also add additional container tags using the `wp_nav_menu_container_allowed_tags` filter.
  
- **Allow Null:** Set this option to **true** or **false** to allow or disallow a **null** value (i.e., no menu selected). When **true**, an empty option will be available for the user to select.

### **Code Example: Basic Setup**

Here's how you can implement the **Nav Menu Field** in a template file:

```php
// Check if the Nav Menu Field has a value
$menu_id = get_field('your_nav_menu_field_name');

if ($menu_id) {
    // Get the menu object or HTML based on your configuration
    $menu_html = wp_nav_menu(array(
        'menu'      => $menu_id,  // Use the menu ID
        'container' => 'nav',      // Use a 'nav' container
        'echo'      => false,      // Don't output directly; return the HTML
    ));

    echo $menu_html; // Output the menu HTML
}
```

## **Common Use Cases**

1. **Use to Output WordPress Navigation Menu Anywhere:**  
   The **Nav Menu Field** allows you to output a WordPress navigation menu in custom locations across your site, such as in custom templates, widgets, or shortcodes.

## **Tips**

- **To Add More Menu Containers:**  
  Use the `wp_nav_menu_container_allowed_tags` hook to add additional allowed container tags for the Nav Menu field. This will enable more flexibility in the menu's wrapper tag.
  
  Example:
  ```php
  function my_custom_menu_container_tags($tags) {
      $tags[] = 'section'; // Adds 'section' as an allowed container tag
      return $tags;
  }
  add_filter('wp_nav_menu_container_allowed_tags', 'my_custom_menu_container_tags');
