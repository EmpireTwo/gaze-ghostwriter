<?php

declare(strict_types=1);

return <<<'TXT'
{{ snippetsBlock }}

Du antwortest als Support.

[ORIGINAL_EMAIL]
Betreff: {{ subject }}
Von: <{{ fromEmail }}>

{{ bodyTextLatest }}
[/ORIGINAL_EMAIL]

{{ replyLanguageDirective }}

{{ regenerateSection }}{{ bareGreetingHint }}

Hinweis: Die Antwortsprache des Entwurfs richtet sich nach dem Kundeninhalt in [ORIGINAL_EMAIL] (Betreff und Text); siehe System-Prompt, oberste Regel. Die Pflichtzeile direkt über diesem Absatz ist für das Modell verbindlich.

Erzeuge eine Antwort-E-Mail (nur Textkörper, keine Betreffzeile im Entwurf) und die geforderten Begründungen.
Passe den Umfang an: Kurze oder nur grüßende Kunden-Mails → ebenfalls kurze Antwort; keine erfundenen nächsten Schritte oder Geschäftsszenarien.
Die Liste referenzierte_chunk_ids muss exakt die IDs der verwendeten Referenz-Snippets enthalten (leer lassen, wenn keine verwendet wurden).
TXT;
