# Topaz
Topaz signature pad examples, for SigPlus and SigWeb code and samples

Intended use - decoding Topaz proprietary format to other formats suitable for displaying inside web browser (SVG, HTML canvas, PDF, PostScript).

The code here works WITHOUT SigPlus and proprietary Topaz drivers and services!!

The code works just for output. Grabbing signature directly from hardware isn't supported (requires Topaz drivers and software).

Contains code to convert SigPlus signature format to:
- SVG
- HTML5 canvas output
- PDF
- PostScript

... using PHP

Files:
- Topaz/sig-2-svg.php
(whole HTML + PHP - working code with sample sig, as is)
- Topaz/sig2svg.download.php
(helper download code to force PDF download in browser)
- Topaz/sig-2-svg.php-stackoverflow.txt
(complete and uncut answer posted originally on Stack Overflow linked here : https://stackoverflow.com/a/61694415/13312932 )

Due to PHP being relatively popular and easy, following the embedded instructions (Stack Overflow answer) it is possible to port these examples to other languages and platforms for other use cases.

Stack Overflow answer explains whole Topaz signature string format.

If anyone ports this code to Python (or anything else for that matter), please make me aware so I can link it in this readme.

Edit 06/2023:
@ChadJPetersen ported it to TypeScript (see Issue #1)

Link to code:
https://gist.github.com/ChadJPetersen/7b1bac66c301f076877c3d917844c7d4
