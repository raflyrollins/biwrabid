type InputErrorProps = {
    message?: string;
    id?: string;
};

export function InputError({ message, id }: InputErrorProps) {
    return (
        <p
            id={id}
            aria-live="polite"
            className="mt-2 min-h-5 text-sm text-fg-danger"
        >
            {message}
        </p>
    );
}
