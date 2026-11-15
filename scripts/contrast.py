#!/usr/bin/env python3
"""
WCAG 2.x contrast check for the design-system colour pairs.

Usage:
    python3 -I scripts/contrast.py            # Markdown table to stdout
    python3 -I scripts/contrast.py --json     # machine-readable

Thresholds (WCAG 2.1/2.2 AA, the level ת"י 5568 requires):
  text        4.5:1  (normal text)
  large       3.0:1  (>= 24px regular or >= 18.66px bold)
  ui          3.0:1  (1.4.11 non-text: borders of inputs, focus rings, icons)
"""
import json
import sys

TOKENS = {
    "paper": "#F7F1E7",
    "paper-2": "#EEE4D4",
    "white": "#FFFFFF",
    "ink": "#1F2421",
    "ink-2": "#4F5550",
    "iris-700": "#4A2D84",
    "iris-600": "#5A3C9A",
    "iris-100": "#ECE5F6",
    "clay-700": "#9E4325",
    "olive-700": "#3D5832",
    "citrus-400": "#F2B33D",
    "citrus-100": "#FBE6B4",
    "sky-200": "#D3E6F4",
    "night": "#1C1830",
    "night-2": "#2B2647",
    "on-night-2": "#CFC8DE",
    "field-border": "#857A6B",
    "error": "#A8261B",
}

# (foreground, background, kind, usage)
PAIRS = [
    ("ink", "paper", "text", "Основной текст"),
    ("ink-2", "paper", "text", "Вторичный текст, подписи"),
    ("ink", "paper-2", "text", "Текст на песочном фоне"),
    ("ink-2", "paper-2", "text", "Вторичный текст на песочном фоне"),
    ("ink", "white", "text", "Текст на белых карточках/полях"),
    ("iris-700", "paper", "text", "Ссылки, акцентный текст"),
    ("iris-700", "white", "text", "Ссылки на белом"),
    ("iris-700", "iris-100", "text", "Чипы/бейджи"),
    ("white", "iris-700", "text", "Кнопка primary"),
    ("white", "iris-600", "text", "Кнопка primary :hover"),
    ("clay-700", "paper", "text", "Годы в таймлайне, акценты"),
    ("olive-700", "paper", "text", "Статус «успех»"),
    ("error", "paper", "text", "Ошибки форм"),
    ("error", "white", "text", "Ошибки на белом поле"),
    ("ink", "citrus-400", "text", "Текст на жёлтом маркере/бейдже"),
    ("ink", "citrus-100", "text", "Текст на светлом маркере"),
    ("ink", "sky-200", "text", "Текст на «небесном» фоне (аутизм)"),
    ("paper", "night", "text", "Текст в тёмной секции"),
    ("on-night-2", "night", "text", "Вторичный текст в тёмной секции"),
    ("citrus-400", "night", "text", "Акцент в тёмной секции"),
    ("paper", "night-2", "text", "Текст на карточке в тёмной секции"),
    ("field-border", "paper", "ui", "Рамка поля формы на бумаге (1.4.11)"),
    ("field-border", "white", "ui", "Рамка поля формы на белом (1.4.11)"),
    ("iris-700", "paper", "ui", "Кольцо фокуса на светлом (2.4.7/1.4.11)"),
    ("citrus-400", "night", "ui", "Кольцо фокуса в тёмной секции"),
    ("iris-700", "white", "ui", "Кольцо фокуса на белом"),
]


def lum(hexc):
    hexc = hexc.lstrip("#")
    rgb = [int(hexc[i:i + 2], 16) / 255 for i in (0, 2, 4)]
    lin = [c / 12.92 if c <= 0.04045 else ((c + 0.055) / 1.055) ** 2.4 for c in rgb]
    return 0.2126 * lin[0] + 0.7152 * lin[1] + 0.0722 * lin[2]


def ratio(a, b):
    la, lb = lum(a), lum(b)
    hi, lo = max(la, lb), min(la, lb)
    return (hi + 0.05) / (lo + 0.05)


def main():
    rows = []
    for fg, bg, kind, usage in PAIRS:
        r = ratio(TOKENS[fg], TOKENS[bg])
        need = 4.5 if kind == "text" else 3.0
        rows.append({"fg": fg, "bg": bg, "fg_hex": TOKENS[fg], "bg_hex": TOKENS[bg],
                     "ratio": round(r, 2), "kind": kind, "need": need,
                     "aa": r >= need, "aaa_text": kind == "text" and r >= 7.0, "usage": usage})
    if "--json" in sys.argv:
        print(json.dumps({"tokens": TOKENS, "pairs": rows}, ensure_ascii=False, indent=1))
        return
    print("| Передний план | Фон | Контраст | Порог | AA | AAA (текст) | Где используется |")
    print("|---|---|---:|---:|:---:|:---:|---|")
    for r in rows:
        print(f"| `{r['fg']}` {r['fg_hex']} | `{r['bg']}` {r['bg_hex']} | {r['ratio']}:1 | {r['need']}:1 | "
              f"{'✅' if r['aa'] else '❌'} | {'✅' if r['aaa_text'] else '—'} | {r['usage']} |")
    bad = [r for r in rows if not r["aa"]]
    print(f"\nИтого пар: {len(rows)}; не проходят AA: {len(bad)}")


if __name__ == "__main__":
    main()
