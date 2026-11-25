<x-password-input id="password" name="password"
    :aria-invalid="$errors->has('password') ? 'true' : null"
    :aria-describedby="$errors->has('password') ? 'password-error' : null"
    :autofocus="$errors->has('password') && ! $errors->has('email')"
    required autocomplete="current-password" enterkeyhint="go"
    placeholder="Enter your password" />
