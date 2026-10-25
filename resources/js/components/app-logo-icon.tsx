import type { ImgHTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

export default function AppLogoIcon({
    className,
    alt = '',
    ...props
}: ImgHTMLAttributes<HTMLImageElement>) {
    return (
        <img
            src="/logo.png"
            alt={alt}
            className={cn('object-cover', className)}
            {...props}
        />
    );
}
