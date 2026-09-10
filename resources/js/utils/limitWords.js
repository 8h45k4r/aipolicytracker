// src/utils/textUtils.js

import DOMPurify from "dompurify";

export function limitWords(html, limit) {
    const text = DOMPurify.sanitize(html).replace(/<[^>]+>/g, ' '); // Remove HTML tags
    const words = text.split(' ');
    return words.slice(0, limit).join(' ') + (words.length > limit ? '...' : '');
}
