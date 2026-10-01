type InputErrorProps = {
    message?: string;
    id?: string;
};

export function InputError({ message, id }: InputErrorProps) {
    if (!message) {
        return null;
    }

    return (
        <p id={id} className="mt-2 text-sm text-fg-danger">
            {message}
        </p>
    );
}
