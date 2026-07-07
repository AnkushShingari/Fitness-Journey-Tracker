# Fitness Journey Tracker

A comprehensive WordPress plugin for tracking fitness journeys with dynamic form configuration, advanced user management, and real-time progress visualization.

## Overview

Fitness Journey Tracker is a fully-featured WordPress plugin that enables users to track their weight loss/gain journey through a mobile-first interface. The plugin features a dynamic form system, session-based authentication, query parameter prefilling, and a powerful admin dashboard with inline editing capabilities.

## Features

### Core Features
- **Dynamic Form System** - Fully configurable forms via `form-config.php` with support for multiple field types
- **Query Parameter Prefill** - Auto-populate registration forms from URL parameters with read-only enforcement
- **Session Management** - Secure token-based user sessions without WordPress authentication
- **Mobile-First Registration** - Simple phone number-based user verification and registration
- **Entry Tracking** - Record weight entries with timestamps and custom metadata
- **Progress Visualization** - Interactive charts showing weight progression with target weight indicators

### Admin Dashboard
- **User Management** - View, edit, restrict, and delete users
- **Pagination** - Efficient server-side pagination (20 users per page)
- **Global Search** - Search across all users by name, mobile, or email
- **Inline Editing** - Edit user profiles and entries directly in the dashboard
- **Current Weight Display** - Real-time display of latest weight from entries
- **Bulk Operations** - Filter by status, restrict/unrestrict users
- **Analytics** - Visual progress charts with historical data

### Advanced Capabilities
- **Dynamic Field Support** - Add unlimited custom fields without code changes
- **Data Persistence** - All dynamic fields automatically saved to database
- **Readonly Prefill** - Query parameter fields are locked but submit correctly
- **Field Type Support** - Text, email, tel, number, textarea, select, radio, checkbox, range, date, URL
- **Validation System** - Client and server-side validation for all field types
- **Safe Updates** - Core fields protected from accidental deletion

## Installation

1. **Upload Plugin**
   - Download the `fitness-journey-tracker` folder
   - Upload to `/wp-content/plugins/` directory
   - OR upload ZIP via WordPress admin: Plugins → Add New → Upload Plugin

2. **Activate**
   - Go to WordPress Admin → Plugins
   - Find "Fitness Journey Tracker"
   - Click "Activate"

3. **Add Shortcode**
   - Create or edit a page
   - Add the shortcode: `[fitness_tracker]`
   - Publish the page

4. **Access Admin Dashboard**
   - Navigate to WordPress Admin → Fitness Tracker

## Usage

### Frontend User Flow

1. **Initial Registration**
   - User visits page with `[fitness_tracker]` shortcode
   - Enters mobile number
   - If new user → proceeds to health profile form
   - If existing user → dashboard loads automatically

2. **Health Profile Form**
   - Full Name (required)
   - Email (optional)
   - Current Weight (required)
   - Target Weight (required)
   - Goal selection
   - Additional dynamic fields from `form-config.php`

3. **User Dashboard**
   - View current weight
   - View target weight
   - Submit new weight entries
   - View progress chart
   - Track journey timeline

### Query Parameter Prefill System

Users can be directed to the registration page with pre-filled data:

```
https://yoursite.com/fitness-tracker/?mobile_number=9876543210&full_name=John%20Doe&email=john@example.com
```

**Behavior:**
- Fields matching query parameters are auto-filled
- Pre-filled fields are displayed as read-only (grayed out)
- Values still submit correctly (using `readonly` for text inputs, hidden inputs for select/radio/checkbox)
- Works with ANY field defined in `form-config.php`

**Supported Parameters:**
- `mobile_number` - Pre-fills and locks mobile input
- `full_name` - Pre-fills name field
- `email` - Pre-fills email field
- Any custom field from your form configuration

**Use Cases:**
- Lead generation campaigns
- Referral tracking
- Pre-registration flows
- Partner integrations

### Entry Submission

After registration, users can submit weight entries:
- Enter current weight
- Submit entry
- View updated progress chart
- Track historical entries in timeline

## Admin Guide

### Accessing Admin Dashboard

Navigate to: **WordPress Admin → Fitness Tracker**

### User Management

**View Users**
- All users displayed in paginated table (20 per page)
- Columns: Mobile, Name, Email, Goal, Current Weight, Target Weight, Entries, Status

**Search Users**
- Enter search term in search box
- Click "Search" or press Enter
- Searches across: Name, Mobile Number, Email
- Search works globally (not limited to current page)

**Filter Users**
- All Users - Show everyone
- Active - Active, non-restricted users
- Restricted - Users who have been restricted
- Completed - Users who completed registration

**Pagination**
- Navigate using Previous/Next buttons
- Jump to specific page numbers
- Shows current range and total count

### Editing User Data

**Inline Profile Editing:**
1. Click user's mobile number or name
2. Modal opens with editable fields
3. Modify any field
4. Click "Save Changes"
5. Data updates immediately

**Inline Entry Editing:**
1. View user details
2. Click "Edit" next to any entry
3. Modify weight or metadata
4. Click "Save"
5. Chart updates automatically

### Restricting Users

**Restrict a User:**
- Click "Restrict" button next to user
- Confirm action
- User is immediately logged out
- User cannot access their dashboard

**Unrestrict a User:**
- Click "Unrestrict" button
- User regains access

### Delete User

- Click "Delete" button
- Confirm action (irreversible)
- User and all entries are permanently removed

### Progress Visualization

**User Detail View:**
- Line chart showing weight over time
- Red dashed line indicating target weight
- Hover over points for exact values
- Responsive design

## Form Configuration

### Location
`/includes/form-config.php`

### Structure

```php
return [
    'health_form' => [
        'sections' => [
            'basic_info' => [
                'title' => 'Section Title',
                'fields' => [
                    'field_name' => [
                        'type' => 'text',
                        'label' => 'Field Label',
                        'required' => true,
                        'placeholder' => 'Enter value'
                    ]
                ]
            ]
        ]
    ]
];
```

### Adding a New Field

1. Open `form-config.php`
2. Add field definition to appropriate section:

```php
'custom_field' => [
    'type' => 'text',
    'label' => 'Custom Field',
    'required' => false,
    'placeholder' => 'Enter custom value'
]
```

3. Field automatically appears in frontend form
4. Data automatically saved to database
5. Field appears in admin edit modal

### Supported Field Types

| Type | Description | Config Options |
|------|-------------|----------------|
| `text` | Single-line text input | `max_length` |
| `email` | Email input with validation | `max_length` |
| `tel` | Phone number input | `max_length` |
| `number` | Numeric input | `min`, `max`, `step` |
| `textarea` | Multi-line text | `max_length` |
| `select` | Dropdown menu | `options` (array) |
| `radio` | Radio buttons | `options` (array) |
| `checkbox` | Checkboxes | `options` (array) |
| `range` | Slider input | `min`, `max`, `default` |
| `date` | Date picker | - |
| `url` | URL input | `max_length` |

### Field Configuration Options

- `type` (required) - Field type
- `label` (required) - Display label
- `required` (boolean) - Whether field is mandatory
- `placeholder` (string) - Placeholder text
- `options` (array) - For select/radio/checkbox
- `max_length` (int) - Maximum character length
- `min`, `max` (number) - For number/range fields
- `step` (number) - Increment for number fields
- `default` (mixed) - Default value

## Data Handling

### Database Tables

**Users Table** (`wp_fjt_user`)
- `mobile_number` (primary key)
- `full_name`
- `email`
- `user_profile` (JSON) - Stores all dynamic fields
- `goal`
- `target_weight`
- `current_step`
- `form_completed`
- `status`
- `is_restricted`
- `time_created`
- `last_updated`

**Entries Table** (`wp_fjt_entries`)
- `id` (auto-increment)
- `mobile_number` (foreign key)
- `weight`
- `entry_meta` (JSON) - Stores dynamic entry metadata
- `entry_type`
- `created_at`

### Dynamic Field Storage

**User Profile Fields:**
- Core fields: `full_name`, `email`, `weight`, `goal`, `target_weight`
- Dynamic fields: Stored in `user_profile` JSON column
- Example:
```json
{
  "height": "175",
  "age": "28",
  "activity_level": "Moderate",
  "dietary_preference": "Vegetarian"
}
```

**Entry Metadata:**
- Core: `weight`, `created_at`
- Dynamic fields: Stored in `entry_meta` JSON column

### Data Safety

- Mobile number cannot be changed (primary identifier)
- Weight entries are append-only
- Core fields protected from deletion
- Dynamic fields merge with existing data (no overwrites)
- All inputs sanitized and validated
- Query parameters sanitized before use

## Technical Notes

### Architecture

- **Dynamic Form System** - Configuration-driven, no hardcoded fields
- **Session-Based Auth** - No WordPress user accounts needed
- **Server-Side Pagination** - Efficient DB queries with LIMIT/OFFSET
- **AJAX-Powered** - Smooth user experience without page reloads
- **JSON Storage** - Flexible schema for unlimited custom fields

### Performance Optimizations

- Pagination limits queries to 20 records
- Search uses database LIKE queries (not JS filtering)
- Latest weight fetched via optimized query (not full entry load)
- Charts use Chart.js library (client-side rendering)
- Session tokens stored in cookies (minimal server overhead)

### Compatibility

- **WordPress:** 5.0+
- **PHP:** 7.4+
- **MySQL:** 5.6+
- **Browsers:** Modern browsers (Chrome, Firefox, Safari, Edge)

### Security

- AJAX nonce verification on all requests
- SQL injection protection via `$wpdb->prepare()`
- XSS prevention via `esc_html()`, `esc_attr()`
- Admin capabilities check (`manage_options`)
- Input sanitization on all user data

### Hooks & Filters

The plugin includes WordPress hooks for extensibility:
- Database table creation on activation
- AJAX endpoints registered via `wp_ajax_*`
- Admin menu registered via `admin_menu` hook
- Scripts/styles enqueued properly

## Support & Development

**Version:** 4.0.11  
**Author:** AnkushShingari  
**License:** GPL v2 or later

For issues, feature requests, or contributions, please contact the plugin administrator.