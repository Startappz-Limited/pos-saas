---
name: bootstrap-development
description: >-
  Styles applications using Bootstrap 5 framework and its utility classes. Activates when adding styles,
  creating responsive layouts, working with components like modals, navbars, cards, forms, or buttons;
  implementing grid systems, spacing utilities, typography, or color schemes; or when the user mentions
  Bootstrap, responsive design, grid layout, components, or any Bootstrap-specific utilities.
---

# Bootstrap Development

## When to Apply

Activate this skill when:

- Adding Bootstrap components or utilities
- Creating responsive layouts with Bootstrap grid
- Working with Bootstrap JavaScript components
- Implementing forms with Bootstrap styling
- Debugging spacing or layout issues with Bootstrap
- Customizing Bootstrap theme variables

## Documentation

Use `search-docs` for detailed Bootstrap 5 patterns and documentation.

## Basic Usage

- Use Bootstrap classes to style HTML. Check and follow existing Bootstrap conventions in the project before introducing new patterns.
- Leverage Bootstrap's utility classes for spacing, colors, and display properties.
- Use Bootstrap's component classes for common UI patterns.
- Ensure responsive behavior using Bootstrap's breakpoint system.

## Bootstrap 5 Specifics

- Always use Bootstrap 5 and avoid deprecated utilities from v4.
- jQuery is not required in Bootstrap 5.
- Use data attributes with `data-bs-*` prefix (not `data-toggle` from v4).

### Grid System

Bootstrap uses a 12-column responsive grid system:

<code-snippet name="Bootstrap Grid" lang="html">
<div class="container">
    <div class="row">
        <div class="col-md-6">Half width on medium screens</div>
        <div class="col-md-6">Half width on medium screens</div>
    </div>
</div>
</code-snippet>

### Breakpoints

| Breakpoint | Class infix | Dimensions |
|------------|-------------|------------|
| Extra small | (none) | <576px |
| Small | sm | ≥576px |
| Medium | md | ≥768px |
| Large | lg | ≥992px |
| Extra large | xl | ≥1200px |
| Extra extra large | xxl | ≥1400px |

## Common Components

### Buttons

<code-snippet name="Bootstrap Buttons" lang="html">
<button type="button" class="btn btn-primary">Primary</button>
<button type="button" class="btn btn-secondary">Secondary</button>
<button type="button" class="btn btn-success">Success</button>
<button type="button" class="btn btn-outline-primary">Outlined</button>
</code-snippet>

### Cards

<code-snippet name="Bootstrap Card" lang="html">
<div class="card">
    <div class="card-header">Header</div>
    <div class="card-body">
        <h5 class="card-title">Card Title</h5>
        <p class="card-text">Card content goes here.</p>
        <a href="#" class="btn btn-primary">Action</a>
    </div>
</div>
</code-snippet>

### Modals

<code-snippet name="Bootstrap Modal" lang="html">
<!-- Button trigger -->
<button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#exampleModal">
    Launch Modal
</button>

<!-- Modal -->
<div class="modal fade" id="exampleModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel">Modal title</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Modal content
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary">Save changes</button>
            </div>
        </div>
    </div>
</div>
</code-snippet>

### Forms

<code-snippet name="Bootstrap Form" lang="html">
<form>
    <div class="mb-3">
        <label for="email" class="form-label">Email address</label>
        <input type="email" class="form-control" id="email" placeholder="name@example.com">
    </div>
    <div class="mb-3">
        <label for="message" class="form-label">Message</label>
        <textarea class="form-control" id="message" rows="3"></textarea>
    </div>
    <button type="submit" class="btn btn-primary">Submit</button>
</form>
</code-snippet>

## Spacing Utilities

Bootstrap uses a spacing scale from 0-5 for margins (m) and padding (p):

<code-snippet name="Bootstrap Spacing" lang="html">
<!-- Margin -->
<div class="m-3">Margin on all sides</div>
<div class="mt-2 mb-4">Margin top and bottom</div>
<div class="mx-auto">Centered horizontally</div>

<!-- Padding -->
<div class="p-3">Padding on all sides</div>
<div class="px-4 py-2">Horizontal and vertical padding</div>
</code-snippet>

| Class | Property |
|-------|----------|
| m | margin |
| p | padding |
| t | top |
| b | bottom |
| s | start (left in LTR) |
| e | end (right in LTR) |
| x | horizontal (left & right) |
| y | vertical (top & bottom) |

## Display & Flexbox

<code-snippet name="Bootstrap Flex" lang="html">
<div class="d-flex justify-content-between align-items-center">
    <div>Left item</div>
    <div>Right item</div>
</div>

<div class="d-flex flex-column gap-3">
    <div>Item 1</div>
    <div>Item 2</div>
</div>
</code-snippet>

## Color Utilities

<code-snippet name="Bootstrap Colors" lang="html">
<!-- Text colors -->
<p class="text-primary">Primary text</p>
<p class="text-success">Success text</p>
<p class="text-danger">Danger text</p>

<!-- Background colors -->
<div class="bg-primary text-white p-3">Primary background</div>
<div class="bg-light text-dark p-3">Light background</div>
</code-snippet>

## Responsive Utilities

<code-snippet name="Bootstrap Responsive" lang="html">
<!-- Hide on small screens -->
<div class="d-none d-md-block">Visible on medium and up</div>

<!-- Stack on mobile, row on desktop -->
<div class="d-flex flex-column flex-md-row">
    <div>Item 1</div>
    <div>Item 2</div>
</div>
</code-snippet>

## JavaScript Components

Bootstrap 5 components can be initialized with data attributes or JavaScript:

<code-snippet name="Bootstrap JS Init" lang="javascript">
// Initialize tooltip
const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]')
const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl))

// Programmatic modal
const myModal = new bootstrap.Modal(document.getElementById('myModal'))
myModal.show()
</code-snippet>

## Customization

Use CSS custom properties to customize Bootstrap:

<code-snippet name="Bootstrap Customization" lang="css">
:root {
  --bs-primary: #0d6efd;
  --bs-secondary: #6c757d;
  --bs-font-sans-serif: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
  --bs-body-bg: #fff;
  --bs-body-color: #212529;
}
</code-snippet>

## Common Patterns

### Navigation Bar

<code-snippet name="Bootstrap Navbar" lang="html">
<nav class="navbar navbar-expand-lg navbar-light bg-light">
    <div class="container-fluid">
        <a class="navbar-brand" href="#">Brand</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link active" href="#">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#">Features</a>
                </li>
            </ul>
        </div>
    </div>
</nav>
</code-snippet>

### Alert Messages

<code-snippet name="Bootstrap Alerts" lang="html">
<div class="alert alert-success alert-dismissible fade show" role="alert">
    Success! Your action was completed.
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
</code-snippet>

## Best Practices

- Use Bootstrap's utility classes before writing custom CSS
- Follow mobile-first responsive design principles
- Leverage Bootstrap's spacing scale for consistency
- Use semantic color classes (primary, success, danger) over custom colors
- Initialize JavaScript components properly to avoid memory leaks
- Test responsive behavior across all breakpoints
