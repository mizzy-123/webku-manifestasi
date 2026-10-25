import { useEffect, useRef, useState } from 'react';
import { Input } from '@/components/ui/input';

type Props = {
    id: string;
    name?: string;
    recentlySuccessful?: boolean;
};

const MAX_DIGITS = 10;

export function formatRupiah(digits: string): string {
    if (digits === '') {
        return '';
    }

    const grouped = new Intl.NumberFormat('id-ID', {
        maximumFractionDigits: 0,
    }).format(Number(digits));

    return `Rp ${grouped}`;
}

export default function RupiahInput({
    id,
    name = 'price',
    recentlySuccessful = false,
}: Props) {
    const [digits, setDigits] = useState('');
    const inputRef = useRef<HTMLInputElement>(null);

    useEffect(() => {
        if (recentlySuccessful) {
            setDigits('');
        }
    }, [recentlySuccessful]);

    const onChange = (event: React.ChangeEvent<HTMLInputElement>) => {
        const element = event.target;
        const cursor = element.selectionStart ?? element.value.length;
        const digitsBeforeCursor = element.value
            .slice(0, cursor)
            .replace(/\D/g, '').length;
        const next = element.value
            .replace(/\D/g, '')
            .replace(/^0+(?=\d)/, '')
            .slice(0, MAX_DIGITS);

        setDigits(next);

        const formatted = formatRupiah(next);

        requestAnimationFrame(() => {
            const input = inputRef.current;

            if (!input) {
                return;
            }

            input.setSelectionRange(
                caretAfterDigits(formatted, digitsBeforeCursor),
                caretAfterDigits(formatted, digitsBeforeCursor),
            );
        });
    };

    return (
        <>
            <Input
                id={id}
                ref={inputRef}
                inputMode="numeric"
                autoComplete="off"
                placeholder="Rp 1.500.000"
                value={formatRupiah(digits)}
                onChange={onChange}
            />
            <input type="hidden" name={name} value={digits} />
        </>
    );
}

function caretAfterDigits(formatted: string, digitCount: number): number {
    if (digitCount <= 0) {
        const firstDigit = formatted.search(/\d/);

        return firstDigit === -1 ? formatted.length : firstDigit;
    }

    let seen = 0;

    for (let index = 0; index < formatted.length; index++) {
        if (/\d/.test(formatted[index])) {
            seen++;
        }

        if (seen === digitCount) {
            return index + 1;
        }
    }

    return formatted.length;
}
