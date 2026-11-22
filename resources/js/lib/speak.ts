/** Phát âm bằng giọng đọc của trình duyệt theo mã ngôn ngữ của thẻ (CR-226). */
export function speak(text: string, lang: string | null) {
    if (
        !lang ||
        typeof window === 'undefined' ||
        !('speechSynthesis' in window)
    ) {
        return;
    }

    const utterance = new SpeechSynthesisUtterance(text);
    utterance.lang = lang;
    window.speechSynthesis.cancel();
    window.speechSynthesis.speak(utterance);
}
