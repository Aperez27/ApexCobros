import sys, os, re, pymupdf
for f in sys.argv[1:]:
    d = pymupdf.open(f)
    toc, vistos = [], set()
    for p in d:
        for l in p.get_links():
            n = l.get('nameddest') or ''
            m = re.fullmatch(r'cap(\d+)(?:-(\d+))?', n)
            if not m or n in vistos:
                continue
            vistos.add(n)
            texto = ' '.join(p.get_textbox(l['from']).split())
            texto = re.sub(r'^(\d+)\s*\.\s*(?=\D)', r'\1. ', texto)        # "1 .Introducción" -> "1. Introducción"
            texto = re.sub(r'^(\d+\.\d+)\s*', r'\1 ', texto).strip()       # "1.1¿Qué…" -> "1.1 ¿Qué…"
            toc.append([2 if m.group(2) else 1, texto, l['page'] + 1, {'kind': pymupdf.LINK_GOTO, 'page': l['page'], 'to': l['to'], 'zoom': 0}])
    if toc:
        d.set_toc(toc)
        tmp = f + '.tmp'
        d.save(tmp, garbage=1, deflate=True)
        d.close()
        os.replace(tmp, f)
    print(f"{os.path.basename(f)}: {len(toc)} marcadores")
