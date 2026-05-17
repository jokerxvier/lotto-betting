import { REGEXP_ONLY_DIGITS } from 'input-otp';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';

type Props = {
    value: string;
    onChange: (value: string) => void;
    disabled?: boolean;
    autoFocus?: boolean;
    id?: string;
    'data-test'?: string;
};

/**
 * 6-slot numeric PIN field. Used by the merged login screen and the
 * Telegram setup-pin flow. Styling uses the project's semantic tokens
 * (border-input) so it picks up light/dark mode automatically.
 */
export default function PinOtpInput({
    value,
    onChange,
    disabled,
    autoFocus,
    id,
    ...rest
}: Props) {
    return (
        <InputOTP
            id={id}
            maxLength={6}
            value={value}
            onChange={onChange}
            disabled={disabled}
            autoFocus={autoFocus}
            pattern={REGEXP_ONLY_DIGITS}
            inputMode="numeric"
            {...rest}
        >
            <InputOTPGroup className="gap-2">
                {[0, 1, 2, 3, 4, 5].map((i) => (
                    <InputOTPSlot
                        key={i}
                        index={i}
                        className="size-11 rounded-md border border-input text-base font-semibold"
                    />
                ))}
            </InputOTPGroup>
        </InputOTP>
    );
}
