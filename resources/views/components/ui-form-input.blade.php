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
    - label: Input label (optional)
    - type: Input type (default 'text')
    - value: Input value
    - placeholder: Placeholder text
    - required: Mark as required (boolean)
    - disabled: Disable input (boolean)
    - help: Help text below input (optional)
    - class: Additional CSS classes for input
    - groupClass: CSS classes for form-group wrapper
--}}

<div class="mb-3 {{ $groupClass ?? '' }}">
    @if (isset($label))
        <label for="{{ $name }}" class="form-label">
            {{ $label }}
            @if ($required ?? false)
                <span class="text-danger">*</span>
            @endif
        </label>
    @endif

    <input type="{{ $type ?? 'text' }}" class="form-control {{ $class ?? '' }} @error($name) is-invalid @enderror"
        id="{{ $name }}" name="{{ $name }}" value="{{ $value ?? old($name) }}"
        @if (isset($placeholder)) placeholder="{{ $placeholder }}" @endif
        @if ($required ?? false) required @endif @if ($disabled ?? false) disabled @endif
        {{ $attributes }}>

    @error($name)
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror

    @if (isset($help))
        <div class="form-text">{{ $help }}</div>
    @endif
</div>
