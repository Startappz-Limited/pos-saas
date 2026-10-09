{{--
    UI Component: Form Select
    
    Usage:
    <x-ui-form-select 
        name="category_id" 
        label="Category"
        :options="$categories"
        value="{{ old('category_id', $product->category_id ?? '') }}"
        required
    />
    
    Or with custom option format:
    <x-ui-form-select name="status" label="Status">
        <option value="active">Active</option>
        <option value="inactive">Inactive</option>
    </x-ui-form-select>
    
    Props:
    - name: Select name attribute
    - label: Select label (optional)
    - options: Array of options [value => label] or Collection (optional)
    - value: Selected value
    - placeholder: Placeholder option text (optional)
    - required: Mark as required (boolean)
    - disabled: Disable select (boolean)
    - help: Help text below select (optional)
    - class: Additional CSS classes
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

    <select class="form-select {{ $class ?? '' }} @error($name) is-invalid @enderror" id="{{ $name }}"
        name="{{ $name }}" @if ($required ?? false) required @endif
        @if ($disabled ?? false) disabled @endif {{ $attributes }}>
        @if (isset($placeholder))
            <option value="">{{ $placeholder }}</option>
        @endif

        @if (isset($options))
            @foreach ($options as $optionValue => $optionLabel)
                <option value="{{ $optionValue }}" {{ old($name, $value ?? '') == $optionValue ? 'selected' : '' }}>
                    {{ $optionLabel }}
                </option>
            @endforeach
        @else
            {{ $slot }}
        @endif
    </select>

    @error($name)
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror

    @if (isset($help))
        <div class="form-text">{{ $help }}</div>
    @endif
</div>
