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
  deco        none   (decorative hairlines: never the only cue, so no threshold; listed for the record)
A pair marked "unused" is listed to show why a combination is not used (e.g. the logo's violet on night);
it is not counted as a failure.
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
    "field-border": "#4F5550",   # --nh-field since v3.2 (research/07 §3 p. 9): 2px, 6.80:1 on paper (v3.1: #857A6B)
    "error": "#A8261B",
    # v2 «ב׳ — בית»: day colour script (top colour of each moment)
    "v2-dawn": "#F6CFAE",
    "v2-noon": "#A9CFEA",
    "v2-golden": "#F0C17E",
    "v2-dusk": "#2B2647",
    # v3 «אותיות שגרים בהן»
    "v3-ink": "#1C1830",
    "iris-text": "#E2DAF0",
    "toggle-dark": "#8F84A6",
    # v3.2 «calm» (research/07 §7.4)
    "hair": "#A19F98",          # --v3-hair: ink at 40% on paper
    "hair-dark": "#5E5967",     # paper at 30% on night
    "logo-violet": "#4F3B97",   # the violet lettering of the logo file (≈)
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
    # v2
    ("paper", "olive-700", "text", "v2: блок «בשפה פשוטה», карточка 02"),
    ("citrus-100", "olive-700", "text", "v2: ссылка в блоке простого языка"),
    ("clay-700", "paper-2", "text", "v2: надзаголовки на песочном фоне"),
    ("ink", "v2-dawn", "text", "v2: «день», утро"),
    ("ink", "v2-noon", "text", "v2: «день», полдень"),
    ("ink", "v2-golden", "text", "v2: «день», после обеда"),
    ("paper", "v2-dusk", "text", "v2: «день», вечер"),
    ("iris-700", "v2-dawn", "text", "v2: акцент в заголовке «дня»"),
    ("citrus-400", "iris-700", "text", "v2: надзаголовок и акцент в CTA «בואו להכיר»"),
    ("ink", "citrus-400", "text", "v2: бегущая лента ценностей, кнопка CTA"),
    # v3
    ("iris-text", "iris-700", "text", "v3: лид и адрес на ирисовом развороте ד"),
    ("white", "iris-700", "text", "v3: заголовок и телефон на развороте ד"),
    ("paper", "v3-ink", "text", "v3: текст на ночном развороте «голоса»"),
    ("field-border", "paper", "ui", "v3: дорожка выключенного переключателя (1.4.11)"),
    ("olive-700", "paper", "ui", "v3: дорожка включённого переключателя"),
    ("toggle-dark", "night", "ui", "v3: дорожка переключателя в подвале"),
    ("ink", "paper", "ui", "v3: линейка разворота и рамка кнопки «בשפה פשוטה»"),
    # v3.2 «calm» (research/07 §7.4)
    ("ink", "citrus-100", "text", "v3.2: весь текст на развороте כ («мёд»), подложка строки индекса"),
    ("ink-2", "citrus-100", "text", "v3.2: мелкий текст на «мёде»"),
    ("iris-700", "citrus-100", "text", "v3.2: ссылки и кольцо фокуса на «мёде»"),
    ("ink-2", "sky-200", "text", "v3.2: мелкий текст на подложке «небо»"),
    ("ink", "paper-2", "text", "v3.2: «מצב רגוע» — разворот כ становится песком"),
    ("olive-700", "paper", "ui", "v3.2: рамка 2px кнопки «בשפה פשוטה» в шапке"),
    ("citrus-400", "iris-700", "ui", "v3.2: кольцо фокуса на ирисовом развороте ד"),
    ("citrus-400", "night", "ui", "v3.2: включённый переключатель в подвале"),
    ("ink", "citrus-100", "text", "v3.2: кнопка «צפו בסרטון» при наведении"),
    ("hair", "paper", "deco", "v3.2: волосная линия между строками (не единственный признак)"),
    ("hair-dark", "night", "deco", "v3.2: волосная линия на тёмном"),
    ("logo-violet", "paper", "text", "v3.2: фиолетовый текст логотипа на бумаге"),
    ("logo-violet", "night", "text", "v3.2: фиолетовый текст логотипа на тёмном — поэтому в подвале только эмблема", False),
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
    for fg, bg, kind, usage, *rest in PAIRS:
        used = rest[0] if rest else True
        r = ratio(TOKENS[fg], TOKENS[bg])
        need = {"text": 4.5, "ui": 3.0}.get(kind)
        rows.append({"fg": fg, "bg": bg, "fg_hex": TOKENS[fg], "bg_hex": TOKENS[bg],
                     "ratio": round(r, 2), "kind": kind, "need": need, "used": used,
                     "aa": None if need is None else r >= need, "aaa_text": kind == "text" and r >= 7.0,
                     "usage": usage if used else usage + " (не используется)"})
    if "--json" in sys.argv:
        print(json.dumps({"tokens": TOKENS, "pairs": rows}, ensure_ascii=False, indent=1))
        return
    print("| Передний план | Фон | Контраст | Порог | AA | AAA (текст) | Где используется |")
    print("|---|---|---:|---:|:---:|:---:|---|")
    for r in rows:
        need = f"{r['need']}:1" if r["need"] else "—"
        aa = "—" if r["aa"] is None else ("✅" if r["aa"] else "❌")
        print(f"| `{r['fg']}` {r['fg_hex']} | `{r['bg']}` {r['bg_hex']} | {r['ratio']}:1 | {need} | "
              f"{aa} | {'✅' if r['aaa_text'] else '—'} | {r['usage']} |")
    bad = [r for r in rows if r["aa"] is False and r["used"]]
    print(f"\nИтого пар: {len(rows)}; используемые пары, не проходящие AA: {len(bad)}")


if __name__ == "__main__":
    main()
