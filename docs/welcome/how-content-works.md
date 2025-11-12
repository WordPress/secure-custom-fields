# Understanding the Relationship Between Post Types, Taxonomies, and Custom Fields

[A TINY CHANGE HERE TO TRIGGER THE DOCS WORKFLOW]

In WordPress, the true power of content modeling comes from combining **Post Types**, **Taxonomies**, and **Custom Fields**. Together, these tools allow you to build virtually any kind of data structure your website needs — from a real estate listing manager to a recipe library or an online product catalog.

This guide will help you understand how each element works, when to use them, and how they interact with each other in practical projects — using movies as our central example.

## Prerequisites

- SCF (Secure Custom Fields) installed and activated
- Administrator access to WordPress
- Basic understanding of WordPress concepts

You can learn more about WordPress basics here:

- [Theme Basics](https://developer.wordpress.org/themes/basics/)
- [Plugin Basics](https://developer.wordpress.org/plugins/plugin-basics/)

## 1. Post Types

**Post types** define **what kind of structured content** you're managing in WordPress.  
By default, WordPress includes two main types:

- **Posts:**  
  These are time-based entries, perfect for blogs, news, or updates. Posts appear in your site's main feed and can be categorized or tagged.

- **Pages:**  
  These are static pieces of content like “About Us”, “Contact”, or “Terms of Service”. They are not organized by date and do not use categories or tags.

However, many websites require **other types of content** that don’t fit as a blog post or a static page. That’s where **custom post types** come in.

---

### When should you create a custom post type?

- When your content doesn’t belong under regular posts or pages
- When you need a separate menu or editing interface in the admin panel
- When you’re managing **structured content** (with specific fields like images, dates, selections, etc.)
- When you want to keep things like `Movies`, `Products`, or `Projects` separate from editorial content

---

### Example: Movies

Let’s say you want to build a catalog of movies on your site.  
Instead of creating a blog post for each movie, you can:

1. Create a custom post type named `Movies`.
2. Add fields like `Title`, `Director`, `Release Year`, `Poster Image`.
3. Display them independently from blog posts or pages.

This gives you full control over how movies are created, organized, and displayed — without mixing them into your main blog.

---

## 2. Taxonomies

Taxonomies define **how content is grouped**. WordPress provides default taxonomies like `Categories` and `Tags`, but you can create your own — such as `Genres`, `Years`, or `Directors`.

**When to create a custom taxonomy:**

- You want to group or filter items based on shared traits
- You need hierarchical grouping (e.g., genre > sub-genre)
- You want users to navigate content by categories meaningful to them

**Example (Movies):**
Create a taxonomy called `Genre` and attach it to the `Movie` post type. This lets users filter all movies tagged as `Action`, `Comedy`, or `Drama` — and even group them by sub-genres if hierarchical.

## 3. Custom Fields

Custom fields store **specific attributes** that aren’t covered by the title or content fields. They are great for metadata that belongs to one item.

**When to use custom fields:**

- You want to add structured data to your post type (e.g., text, numbers, images)
- You want each item to have unique values (not shared or grouped like taxonomies)

**Example (Movies):**
Use custom fields to store each movie's `Director`, `Release Year`, `Duration`, `Trailer URL`, and `Poster Image`. These are all values unique to each movie and not ideal for filtering across many movies like taxonomies would be.

## 4. Why This Matters

When used together, these three features form a powerful content management structure. Here’s how it looks in practice for a movie website:

- A **Movie** post type
- With a **Genre** taxonomy (e.g., Action, Sci-Fi, Romance)
- And custom fields for:

  - `Director`
  - `Duration`
  - `Release Year`
  - `Trailer URL`

This enables you to:

- Create a dedicated admin panel for movies
- Allow users to browse by genre
- Display rich details per movie on the frontend
- Build custom archive templates and filtering tools

Whether you're building a streaming catalog, a film festival archive, or a movie blog — combining post types, taxonomies, and custom fields allows WordPress to adapt far beyond traditional blogs.

---

### Real-World Example: Real Estate Website

**Post Type:** `Property`

Each property is its own entry, allowing the team to manage listings independently.

**Taxonomies:**

- `Property Type` (e.g., House, Apartment, Commercial)
- `Location` (e.g., City or Zone)

**Custom Fields:**

- `Price`
- `Bedrooms`
- `Bathrooms`
- `Square Footage`
- `Gallery`
- `Contact Email`

This setup allows site visitors to:

- Filter properties by type or location
- View detailed information per listing
- Submit inquiries directly from the property page

---

### Real-World Example: User Check-In/Check-Out System

**Post Type:** `User Visit`

Each entry represents a single visit by a user.

**Taxonomies:**

- `Department` (to group visits by area)

**Custom Fields:**

- `User Name`
- `Check-In Time`
- `Check-Out Time`
- `Purpose of Visit`
- `Approved By`

This setup enables admins to:

- Track entry and exit logs
- Generate visit reports by department
- Display current on-site users if needed

## Advanced Example: Relational Content with Bidirectional Fields

In more complex systems, post types may need to relate to each other. You can use **bidirectional relationships** (also known as post-to-post relationships) to build powerful, interconnected structures.

### Example: University Course Management System

**Post Types:**

- `Course`
- `Instructor`
- `Student`

**Taxonomies:**

- `Department` (e.g., Science, Arts)

**Custom Fields:**

- `Course`:

  - `Course Code`
  - `Credits`
  - `Instructor` (relationship to Instructor post)
  - `Enrolled Students` (bidirectional relationship to Student posts)

- `Instructor`:

  - `Full Name`
  - `Assigned Courses` (bidirectional relationship to Course posts)

- `Student`:

  - `Name`
  - `Courses Enrolled` (bidirectional relationship to Course posts)

This setup enables features like:

- Displaying all students enrolled in a course
- Showing which instructor teaches each course
- Generating student dashboards with course schedules

You can manage this using Secure Custom Fields with relationship-type fields.

---

## Next Steps

- [Learn how to create your first custom post type](../tutorials/first-post-type.md)
- [Explore how to build a custom taxonomy](../tutorials/first-taxonomy.md)
- [Add and manage custom fields with SCF](../tutorials/first-custom-field.md)
