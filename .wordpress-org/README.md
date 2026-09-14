# `.wordpress-org/`

Listing artwork for the wordpress.org plugin directory. **Nothing in this directory is shipped
to users.** `.distignore` excludes it from the release zip, and the deploy workflow syncs it to
the `assets/` folder at the **root** of the plugin's SVN repository — a sibling of `trunk/` and
`tags/`, never inside them.

Files here are live the moment they are committed: the directory page re-reads them without a
new plugin release, so artwork can be corrected between versions.

## Required files

| File | Size (px) | Notes |
|---|---|---|
| `icon-128x128.png` | 128 × 128 | Search results and the plugin card. |
| `icon-256x256.png` | 256 × 256 | Retina. Same artwork, twice the pixels. |
| `banner-772x250.png` | 772 × 250 | Header of the plugin page. |
| `banner-1544x500.png` | 1544 × 500 | Retina banner. |
| `screenshot-1.png` … `screenshot-6.png` | see below | One per caption line in `readme.txt`. |

`icon.svg` may replace the two PNG icons, but `icon-256x256.png` must still be present as the
fallback. There is no SVG option for banners.

`.jpg` is accepted in place of `.png` for every file above; `.gif` is accepted for screenshots
only. Pick one extension per file — two files differing only in extension is undefined.

## Screenshots

Numbering is the whole contract: `screenshot-3.png` is described by the **third** line under
`== Screenshots ==` in `readme.txt`. A missing file leaves a broken image on the listing, and an
extra file is never displayed. Add the `readme.txt` captions only when their corresponding
screenshots are present. No listing screenshots are currently checked in.

No fixed dimensions are imposed. Capture at a 2× device pixel ratio and keep every screenshot
the same aspect ratio; 1280 × 960 works well and stays legible when the directory scales it
down to roughly 770 px wide.

Screenshots must show this plugin's real interface. Before capturing, point the plugin at the
**sandbox** environment and use placeholder signer names and `example.com` addresses — a
screenshot is public forever, and a real address or a production document id cannot be taken
back. Crop or blank the account id and never show an API key field in a state that reveals
characters.

## Optional

`blueprint.json` enables the directory's live preview (WordPress Playground). It is off until
one exists here. Because the plugin cannot do anything without an Assinafy API key, a preview
would demonstrate only the settings screen; it is deliberately not provided.
