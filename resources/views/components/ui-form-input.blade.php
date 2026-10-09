{{--
    UI Component: Form Input

    Usage:
    <x-ui-form-input
        name="product_name"
        label="Product Name"
        value="{{ old('product_name', $product->name ?? '') }}"
        placeholder="Enter product name"
        required
    />

    Props:
    - name: Input name attribute
    - id: Input id (optional, defaults to name; set it when two forms on a page share a field name)
    - label: Input label (optional)
    - type: Input type (default 'text')
    - value: Input value
    - placeholder: Placeholder text
    - required: Mark as required (boolean)
    - disabled: Disable input (boolean)
    - help: Help text below input (optional)
    - errorBag: Named error bag to read this field's error from (default 'default')
    - class: Additional CSS classes for input
    - groupClass: CSS classes for form-group wrapper

    Any other attribute (autocomplete, autofocus, min, ...) is passed to the input.
--}}

@props([
    'name',
    'id' => null,
    'label' => null,
    'type' => 'text',
    'value' => null,
    'placeholder' => null,
    'required' => false,
    'disabled' => false,
    'help' => null,
    'errorBag' => 'default',
    'class' => '',
    'groupClass' => '',
])

@php($id ??= $name)

<div class="mb-3 {{ $groupClass }}">
    @if (isset($label))
        <label for="{{ $id }}" class="form-label">
            {{ $label }}
            @if ($required)
                <span class="text-danger">*</span>
            @endif
        </label>
    @endif

    <input type="{{ $type }}" class="form-control {{ $class }} @error($name, $errorBag) is-invalid @enderror"
        id="{{ $id }}" name="{{ $name }}" value="{{ $value ?? old($name) }}"
        @if (isset($placeholder)) placeholder="{{ $placeholder }}" @endif
        @if ($required) required @endif @if ($disabled) disabled @endif
        {{ $attributes }}>

    @error($name, $errorBag)
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror

    @if (isset($help))
        <div class="form-text">{{ $help }}</div>
    @endif
</div>
