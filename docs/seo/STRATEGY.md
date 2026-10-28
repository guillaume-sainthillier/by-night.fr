# SEO strategy of by-night.fr

Drafted 2026-10-28. The machine-readable version is [`strategy.yaml`](strategy.yaml): clusters, targets, page types,
indexing policy, measurement and KPIs. seo-monitor reads its `clusters` every Monday. This file explains why: what
the data says, what was chosen, the 90-day roadmap, the KPIs, the risks, what could not be verified and the decisions
left to Guillaume.

## Decisions in one screen

1. **By Night is a long tail of event pages.** 93% of Google clicks land on event pages (5,444 of 5,850 in 95 days).
   City hubs get 3.5%, the home page 1.3% (brand), and venue, tag and type pages together less than 1.5%. Clicks
   are spread over thousands of towns: the top 50 locations carry half of them, and it takes 1,500 locations to
   reach 88%. Templates, imports and indexing rules move the site; editing pages one by one does not.
2. **Google clicks fell 40% year on year** (August 1 → October 27: 9,302 in 2025, 5,554 in 2026; impressions −39%). This was not an outage:
   there is no dip on 2026-08-05, the day the shared OVH server took offre-parrainages.fr down. The site lost two
   things:
   - the 2025 one-off hits (a store opening, a recruitment day, Coldplay), which are luck;
   - the pages of **new events**: events created in the previous six months made 600 clicks a month in winter, 100-200
     in April-July 2026, 600 again in August-September, and 300 in October.

   Getting new events crawled, indexed and ranked fast is the main lever.
3. **Bing sends three times Google's clicks** (about 169 a day against 50 in October). It crawls 350-420k pages a day
   and holds 2.93 M URLs of the site. It saw ~40,000 5xx a day from 2026-09-04 to 2026-10-16, and ~2,300 a day
   since. Any indexing decision must be checked on Bing first, and Bing needs its own fast lane: IndexNow.
4. **Index bloat is real, but it is not where the clicks are.**
   - The sitemap has 305,139 URLs, of which 9,121 (3%) were shown in 90 days.
   - Yet 92% of the clicks went to URLs that are not in the sitemap: past events, which stay indexable since the
     2026-09-22 decision.
   - So the policy is to submit less but better (thresholds for tags, places, types and hubs, honest lastmod), keep
     the past events that earn impressions, and take out only empty, duplicate, spam and tool pages.
   - Noindexing past events with no demand stays a pilot, decided once Bing's page data is in hand.
5. **The generic "what's on" demand exists, and By Night does not convert it.**
   - « ce week end » had 12,327 impressions in 16 months, « sortir ce soir » 5,723, « sortir à bordeaux » 2,147:
     about 30,000 impressions in 95 days at an average position of 32, with 98 clicks.
   - The new date pages (2026-10-22) and type pages (2026-10-27) are the right format.
   - Hubs of medium cities rank better than the metros' (Mulhouse 12-15, Roanne 23), and tourism offices and city
     media hold the metro head terms. Growth targets are modest for metros and higher for medium cities.
6. **Venue names are a hidden demand answered by the wrong page.** « triskell montpellier » (3,318 impressions at
   position 8), « ferme des briques » (2,688), « stade jean pierre rives » (2,059) and « apero du mistral » (6,252)
   all land on old event pages and get almost no clicks. Place pages, 40,280 of them in the sitemap, made 10 clicks
   in 95 days. The place page should own the venue's name: better title, history of past events, and indexable
   between two events.
7. **Template work comes first; there is no blog.** The plan, in order:
   - event title with the town and the year;
   - a past event that leads to its venue and to what's on next;
   - JSON-LD timezone and an ItemList on listings;
   - hub title test (A/B by city list);
   - place page;
   - a whitelist of tags;
   - noindex on internal search;
   - seasonal events ready four weeks ahead (Noël and réveillon were 24% of December 2025's event clicks).
8. **10 clusters and 64 targets.** The clusters are date pages, seasonal pages, metro hubs, medium-city hubs, brand,
   type pages, venues, big-city events, pages to prune and the long tail.
   - The named clusters claim 53% of the clicks. The long-tail catch-all (`/*`) takes the other 47%.
   - "(no cluster)" is therefore empty by construction (§ 3).
   - KPIs are set per cluster, with reviews on 2026-11-23, 2026-12-21 and 2027-01-25.
   - Holding Google clicks flat through the seasonal trough is the site-level goal: last year January ran 38% below
     October.

## 1. Data and method

| Source | What it gave | Limits |
|---|---|---|
| Search Console API, `sc-domain:by-night.fr`, 2025-06-16 → 2026-10-27 | daily totals; page × month (537k rows); query × month (229k); query × page per half-year (181k) | 16 months only: one seasonal cycle, mixed with the decline |
| seo-monitor store (`data/seo.sqlite`, 2026-07-25 → 2026-10-27) | page × day (205k rows), query × page × day (78k), the 305,139 sitemap URLs (fetched 2026-10-28), Bing daily traffic and crawl | Google hides most long-tail event queries: only 11% of clicks and 35% of impressions carry a query. Page figures are the reference |
| 2026-10-28 opportunities report (before and after this strategy) | pages to push, weak snippets, cannibalization, uncovered, the cluster scorecard | 28-day windows |
| Code of origin/main (49f23f48): `src/SEO/*`, `src/EventSubscriber/SitemapSuscriber.php`, `src/Controller/Location/*`, templates, git history | page types, titles, robots rules, sitemap filters, the dates of every URL change | production counts (cities with ≥ 15 events, places with history) are not in the code |
| WebSearch / WebFetch on 14 French queries, curl of 6 production pages | who ranks, page types, structured data of competitors | the search tool is not google.fr and does not show SERP features. Treat it as indicative |

No paid keyword tool was used. **Every volume in this document is a Search Console impression count**, which
undercounts demand wherever the site ranks beyond page 3. Nothing here is an estimated search volume. Event IDs
increase with time, so "events created in the last six months" is approximated by an ID above the highest ID seen six
months earlier (SQL in § 9).

## 2. Findings

### 2.1 Where the clicks come from

95 days (2026-07-25 → 2026-10-27), Google:

| Page type | Clicks | Impressions | Distinct pages shown | Last 28 days (clicks / impr.) |
|---|---:|---:|---:|---:|
| Event `/{city}/soiree/…` | 5,444 | 315,223 | 90,918 | 1,209 / 69,066 |
| City hub `/{city}` (incl. `/{city}/`, pagination) | 206 | 30,448 | 852 | 78 / 9,721 |
| Home `/` | 76 | 4,842 | 1 | 45 / 1,692 |
| Place `/{city}/agenda/sortir-a/…` | 47 | 5,718 | 2,210 | 17 / 1,192 |
| Former agenda `/{city}/agenda` (301 since 2026-10-05) | 38 | 2,893 | 955 | 11 / 739 |
| Tag `/{city}/agenda/tag/…` | 19 | 737 | 384 | 14 / 397 |
| Type `/{city}/agenda/sortir/…` | 17 | 881 | 339 | 15 / 565 |
| CMS `/p/…`, members, organizers, date pages | 3 | 245 | 16 | |

- **Event clicks are thin and wide.**
  - 3,912 event pages made at least one click in 95 days. The top 100 made 970 clicks, the top 1,000 made 2,532.
  - Of the 1,410 clicks of the last 28 days, the top 20 locations made 549. You need 250 locations for 964 clicks
    and 1,500 for 1,246.
  - Paris leads (420 clicks in 95 days), then `/c--france` (national events, 292), Nantes 214, Toulouse 151,
    Marseille 137, Lyon 125, Lille 116 and Bordeaux 98.
- **What event-page queries look like.** Of the queries Google does show for event pages:
  - most are **names**: of an event, a venue, an organiser or a performer;
  - 45% of their impressions contain the town's name;
  - 2.3% contain a year, and those carry 10% of the clicks (« run from the dead 2026 », « vogue pontaix 2026 »).
- **CTR is low at every position.** Event pages get 1.65% at positions 4-10 and 5.1% at positions 1-3. A large share
  of these impressions answer a venue or person query with an old event.
- **Old events still earn.** Events with an ID below 1 M (the oldest: a 2014 TV report is ID 112,560) made 2,256 of the
  5,444 event clicks (41%), and almost none of them is in the sitemap (6 of their 22,934 pages). Examples: a 2014 TV report in Nantes (50 clicks), a weekly party in
  Paris, libertine club nights in Aix. This is what the 2026-09-22 revert (55b1e3a6) measured, and it is right.

### 2.2 Sixteen months: what fell, and why

| Month | Clicks | Impressions | Avg pos. | Event clicks | of which events < 6 months old |
|---|---:|---:|---:|---:|---:|
| 2025-07 | 3,651 | 332,406 | 31.0 | 3,213 | (not computable before Dec.) |
| 2025-09 | 3,314 | 152,903 | 21.3 | 3,113 | |
| 2025-10 | 2,997 | 147,504 | 19.0 | 2,777 | |
| 2025-12 | 2,068 | 125,461 | 25.1 | 1,944 | 635 |
| 2026-02 | 1,900 | 123,730 | 27.6 | 1,739 | 633 |
| 2026-04 | 1,773 | 93,015 | 12.2 | 1,608 | 196 |
| 2026-06 | 1,500 | 107,700 | 10.6 | 1,414 | 109 |
| 2026-07 | 1,334 | 76,621 | 12.3 | 1,221 | 98 |
| 2026-08 | 2,118 | 140,882 | 17.3 | 2,013 | 536 |
| 2026-09 | 2,096 | 116,030 | 12.1 | 2,014 | 601 |
| 2026-10 (1-27) | 1,340 | 78,581 | 14.0 | 1,162 | 300 |

Three engines make the event traffic. They behaved differently.

1. **Evergreen old events** (venue, recurring night, organiser names): 700-1,000 clicks a month, stable from 2025 to
   September 2026, and 477 in October 2026 (to watch).
2. **New events.** Their clicks collapsed between April and July 2026: 100-200 a month, from 2,300-4,000 pages shown,
   against 7,000-14,000 pages in winter. They came back in August, and are falling again in October (8,510 pages).
   - The distinct event pages Google shows per week jumped from 6,200 (week of 2026-08-03) to 17,800 (week of
     08-10), then slid to 7,100 (week of 10-19).
   - Nothing in the code dates either move. The commit "move from aws to ovh + cloudflare" was written on 2026-04-11
     but committed on 2026-09-04, so its deploy date is unknown. The Bing 5xx period (09-04 → 10-16) and the five URL
     changes of October overlap the autumn slide. See § 12.
3. **One-off hits.** In August-October 2025 the top 200 pages made 3,639 clicks; the same pages made 524 a year
   later. The biggest were:
   - « ouverture de la boulangerie Feuillette à Carcassonne »: 409 clicks;
   - « Burger King ouvre à Fontenay-le-Comte »: 342;
   - Coldplay at the Stade de France: 347;
   - Lidl's recruitment day in La Londe: 134.

   Store openings and recruitment days posted as events are real local demand. They cannot be planned, but a fast
   indexing pipeline catches them (« Burger King Vauvert » showed up in October 2026).

Also:
- **City hubs never carried the site.** They made 30-120 clicks a month in 16 months, at positions 40-50 in 2025 and
  25-30 since April 2026.
- **Place pages fell** from 3,900 pages shown and 155 clicks (July 2025) to 130 pages and 7 clicks (July 2026).
- **No 2026-08-05 outage on by-night.** Daily impressions were 2,267 on 08-04, 2,654 on 08-05 and 4,055 on 08-06,
  then rose. Either by-night was not on that server or Cloudflare's cache hid the outage from crawlers
  (stale-if-error 86400 on listings, one day / one week on event pages). See § 12.
- **Bing grew while Google fell.** Bing clicks went from 3,020 in October 2025 to 5,400-5,900 a month in April-June
  2026, and 4,235 for October 1-25, 2026.

### 2.3 Bing

| Month | Bing clicks | Bing impressions | Crawled/day | 5xx/day | Bing index |
|---|---:|---:|---:|---:|---:|
| 2026-04 | 5,892 | 348,062 | 150,164 | 1,356 | 2.07 M |
| 2026-06 | 5,832 | 251,815 | 236,092 | 5,416 | 2.55 M |
| 2026-08 | 3,839 | 140,427 | 203,730 | 188 | 2.73 M |
| 2026-09 | 3,565 | 170,239 | 185,533 | 46,317 | 2.68 M |
| 2026-10 (1-25) | 4,235 | 172,390 | 310,512 | 20,840 | 2.93 M |

- From 2026-09-04 to 2026-10-16 about 40,000 of the ~200,000 daily crawled pages answered a 5xx (20%). Since
  2026-10-17 it is ~2,300 a day, or 0.6%.
- Bing clicks did not drop during the incident. They rose in October, after the redesign.
- seo-monitor stores Bing totals only, so which pages Bing sends traffic to is unknown. If they are past events, a
  Google-motivated noindex would cut the bigger engine.
- That is why the past-event pilot is conditional on the decision to store Bing's page data (§ 13).

### 2.4 The index: what is submitted, what is shown

The sitemap of 2026-10-28, against Google impressions in the 95-day store:

| Section | Sitemap URLs | Shown in 95 d | Clicks | Rule today |
|---|---:|---:|---:|---|
| Events | 237,637 | 8,273 | 366 | published, not duplicate, ended < 30 days ago |
| Places | 40,280 | 390 | 10 | ≥ 1 upcoming event |
| Hubs and other pages | 11,407 | 242 | 86 | city with ≥ 1 upcoming event |
| Tags | 10,286 | 61 | 7 | city × tag with ≥ 1 upcoming event |
| Types | 4,985 | 21 | 1 | ≥ 15 upcoming, ≥ 1 of the type (2026-10-27) |
| Date pages | 400 | 1 | 0 | ≥ 100 upcoming (2026-10-22) |
| Members, organizers, CMS | 144 | 3 | 2 | |

- **Clicks come from outside the sitemap.** Of the 5,850 clicks of 95 days, 5,378 landed on URLs that are not in
  today's sitemap: past events, and old hub URLs before the 2026-10-05 merge.
- **The sitemap's main cost is noise, not clicks.**
  - 226,664 event URLs carry an October 2026 lastmod. The backfills (images, websites, sessions) moved `updatedAt`,
    so lastmod no longer means "changed".
  - Tag pages are mostly DataTourisme descriptors that nobody searches: « dans le village » ×464, « à la campagne »
    ×308, « en ville » ×264, « art de vivre », « culture ».
  - Some tags are synonyms of a type page (concert--5, concerts--840, spectacle--1).
  - A generous whitelist of searched tags keeps 4,185 of the 10,286.
- **Hubs of big cities with zero impressions.** Toulon, Pau, La Rochelle, Troyes, Colmar, Valence, Bayonne, Vannes,
  Saint-Malo and Chartres had no hub impression in 95 days, while Sète and Roanne had hundreds. Either Google has not
  indexed them or ranks them beyond page 10. Check them with URL Inspection.
- **Google shows 3% of the submitted events.** Event descriptions come from OpenAgenda and DataTourisme, the same
  text the tourism offices and OpenAgenda publish. When two pages carry the same text, Google usually ranks the
  original. By Night's own value has to show on the page: the venue's other events, nearby events, every ticketing
  site with prices, the hours, the map, the "J'y vais" count.

### 2.5 Demand By Night does not convert yet

**Generic "when / what" intents.** 16 months, word-matched like seo-monitor:

| Intent | Impr. 16 m | Pos. | Clicks | Answered today by |
|---|---:|---:|---:|---|
| « … ce week end » | 12,327 | 46 | 16 | city hubs (the date pages are 6 days old) |
| « sortir ce soir … » | 5,723 | 42 | 34 | hubs |
| « soirée ce soir … » | 5,534 | 31 | 102 | hubs |
| « que faire aujourd'hui … » | 3,373 | 47 | 6 | hubs |
| « concert … » (any) | 25,568 | 42 | 402 | events and hubs |
| « marché … » (weekly markets, Christmas markets) | 25,940 | 22 | 23 | event pages, near 0 CTR |
| « brocante », « loto », « salon » | 4,947 / 2,902 / 4,555 | 16 / 14 / 31 | 10 / 18 / 36 | event pages |

By city, the hub's head terms, as « sortir à X » in exact match (16 months; 95 days in brackets):

| City | Impr. | Pos. | Note |
|---|---:|---:|---|
| Reims | 2,689 (164) | 56 (32) | the largest exact head term |
| Bordeaux | 2,147 (433) | 42 (37) | biggest hub overall: « sortir bordeaux » in word match 4,828 |
| Strasbourg | 742 (211) | 43 (31) | « concert strasbourg » flagged as uncovered |
| Nantes | 715 (127) | 46 (37) | |
| Rouen | 704 (142) | 41 (32) | |
| Toulouse | 639 (31) | 62 (46) | « soirée toulouse » in word match 1,847 at 58 |
| Lyon | 553 (194) | 48 (40) | |
| Nancy, Metz, Besançon, Béziers, Caen, Aix | 290-527 each | 36-47 | positions 28-42 in 95 days |

**Venue names.** Old event pages rank 8-14 on these queries and get no clicks:

| Query | Impr. 16 m | Pos. | Clicks |
|---|---:|---:|---:|
| apero du mistral | 6,252 | 10 | 22 |
| la lanterne aix | 4,262 | 15 | 10 |
| triskell montpellier | 3,318 | 8 | 0 |
| ferme des briques | 2,688 | 12 | 0 |
| stade jean pierre rives | 2,059 | 10 | 0 |
| salle limayrac | 1,369 | 10 | 0 |
| bokit montpellier | 1,468 | 11 | 2 |
| club hippique pibrac | 837 | 13 | 0 |
| médiathèque hélène berr | 780 | 14 | 0 |

A place page exists only for venues with an upcoming event: the Hélène Berr médiathèque has one, the Triskell does not.
Some place records are addresses or towns (« 300 rte de levignac 31820 pibrac france », « besancon-centre-ville »).

### 2.6 SERP and winnability (indicative)

The searches ran on a non-Google index on 2026-10-28. By-night.fr appeared on none of the 14 queries, not even its
brand, which says more about that index than about Google (Search Console has the brand at position 6).

| Query family | Who wins | Format |
|---|---|---|
| « que faire à Toulouse ce week-end », « sortir à X » | tourism offices (toulouse-tourisme, visiterlyon, Paris je t'aime), Sortir à Paris, Billetréduc `/x/weekend`, cityzeum, fest.fr | a **fixed URL refreshed weekly** (Sortir à Paris reuses one article ID and rewrites the title every week); dated listicles rank only for dated queries |
| « agenda concert X », « concert X » | JDS (`/toulouse/agenda/musique-et-concerts`, MusicEvent JSON-LD ×25 on the listing), fest.fr (Event microdata), Songkick, Bandsintown, Fnac Spectacles, Shotgun, city venues | listings marked up as events |
| event names | the organiser or city hall (paris.fr/evenements with Event JSON-LD), then big media and agendas | |
| seasonal (« marché de noël reims ») | evergreen guides refreshed each year, not the agendas | |

What this means for By Night:

- **The format is right.** The fixed `/x/ce-week-end` and `/x/ce-soir` URLs, with the count and the dates in the
  description, match what the leaders do.
- **What is missing compared with JDS, fest.fr and AllEvents:**
  - markup on the listings (ItemList, at least);
  - copy of its own on hubs and tag pages;
  - a reason to prefer By Night's event page over the source's: the cross-source data listed in 2.4.
- **Winnability.**
  - Metro head terms: hard. Tourism offices and city media are older, more trusted and edited.
  - Medium cities: realistic, since Mulhouse, Roanne and Sète already rank 12-29.
  - Venue names and the long tail: realistic, since By Night is already on page 1 and loses only on the answer.

### 2.7 Cannibalization

- **Within an event:** the same real event exists twice across sources. The 2026-10-28 report finds two weekly
  « marché hebdomadaire de Houlgate » pages at 49% each, and two Auchy-les-Mines riding-school stages sharing « centre
  équestre de la ferme des briques ». Fix it with deduplication, plus the place page for the venue name.
- **Hub vs date page vs type page:** today the hub answers everything: « bordeaux ce soir » → `/bordeaux/`,
  « concert lyon » → `/lyon/`. The new pages must take these queries over. The YAML targets point at the date and
  type pages, so seo-monitor flags every query still answered by the hub ("wrong page"). Allow 8 weeks before acting.
- **Hub vs legacy URLs:** `/x/`, `/x/agenda` and `/x` split impressions until 2026-10-05. The bare URL took over in
  the 3 weeks since (bare 935 impressions vs trailing-slash 1,273 in the week of 10-19).

### 2.8 Quality risks found in the data

- **Spam.** « offre de prêt entre particulier sérieux et honnête » is a loan-scam "event" in Paris, indexed at position
  64, with 16 clicks in 95 days on its own query and the scammer's e-mail address as a query. One page is enough to
  matter for site reputation; delete it and review members' events.
- **Adult content.** Libertine clubs, « massage érotique / tantrique », BDSM nights, naturist réveillons: their names
  bring steady clicks (« la lanterne aix » 4,262 impressions; queries with these words: 1,696 impressions and 45
  clicks in 95 days). AdSense runs on every page, and its policies restrict ads next to sexual content. This is a
  decision, not an SEO tactic (§ 13).
- **Data quality.** Place names stored as addresses or towns; old events whose title carries a phone number
  (« 0767192895 »: 537 impressions on two Breton events); the same event under two IDs.

## 3. Clusters and why

| Cluster | Prio. | Goal | Last 28 d (clicks / impr.) | Why (data) |
|---|---:|---|---:|---|
| `ce-soir-ce-week-end`: 48 date pages of 24 cities | 1 | launch | 0 / 0 | the biggest generic demand (« ce week end » 12k, « sortir ce soir » 5.7k, « soirée ce soir » 5.5k in 16 months), answered today by hubs at 30-44; pages live since 2026-10-22 |
| `saisons-fetes`: Noël, Halloween, Nouvel An tag pages; seasonal intent targets | 1 | grow | 0 / 0 | Noël + réveillon events: 24% of December 2025's event clicks; JEP 101 clicks in September 2026; the 90 days cover Christmas and New Year |
| `hubs-metropoles`: the 11 biggest cities' hubs (+ their former `/agenda`) | 1 | grow | 16 / 3,509 | « sortir à bordeaux » 2,147, « sortir à strasbourg » 742 in 16 months, at positions 30-45; strong competitors: modest targets |
| `hubs-villes-moyennes`: 45 medium cities' hubs | 1 | grow | 20 / 3,880 | better positions (Mulhouse 12-15, Roanne 23, Sète 14), « sortir à reims » 2,689; less competition |
| `marque-accueil`: home | 2 | defend | 45 / 1,646 | « by night » 16,206 impressions, position 7, shared with the « Toulouse by night » tour |
| `sorties-par-type`: type pages of 17 cities | 2 | launch | 0 / 77 | « concert lyon » 1,436, « concert toulouse » 785 at 40-60, answered by hubs or one event; JDS and fest.fr hold them with listings |
| `lieux-salles`: 9 place pages + 8 venue-name targets | 2 | grow | 0 / 31 | thousands of impressions on venue names at positions 8-14 with no clicks; place pages make 10 clicks in 95 days |
| `evenements-grandes-villes`: everything else under the ~45 big cities | 2 | defend | 589 / 30,629 | 42% of clicks; template work shared with the long tail; targets are its evergreen pages |
| `elagage-index`: `/c--*` legacy, `/recherche`, members, organizers, the spam page | 3 | prune | 71 / 4,166 | must fall to ~0 (301s, noindex, 410) |
| `evenements-longue-traine`: `/*`, every other town | 3 | defend | 669 / 40,741 | 47% of clicks; managed through templates and imports; targets are small-town intents (marché, brocante, loto, salon) |
| (no cluster) | | | 0 / 0 | empty by construction |

**Why a catch-all.**
- seo-monitor's patterns are path prefixes, and event URLs start with the town. To claim 80% of the clicks with
  named towns you would need about 800 prefixes, and the list would change every month: Valensole, Auchy-les-Mines and
  Pibrac are in the top 50 for one event.
- So the long tail is one cluster, `evenements-longue-traine` with the pattern `/*`. It must stay the last cluster
  with the lowest priority, because seo-monitor gives a page to the first cluster that matches. Its priority 3 is that
  technical constraint, not its weight.
- Two signals replace the empty "(no cluster)" row: the catch-all's share of clicks (alarm above 55%, 47% today) and
  the report's new-pages list.

**Ordering.**
- Explicit page lists come before the city prefixes: date pages, seasonal tags, hubs, type pages, venues. Otherwise
  `/bordeaux/*` would swallow `/bordeaux/ce-soir`.
- The spam URL is claimed by `/paris/*` first in the scorecard, and is listed in `elagage-index` for the record.

**Targets.**
- Hub head terms use `match: exact` (« sortir à bordeaux »), so that « sortir à bordeaux ce soir » counts for the date
  page and not as a wrong page of the hub.
- « ce week end » is two words for seo-monitor. A query typed as « weekend » is another word and is not counted.
- Seasonal and venue targets without a `page` measure the intent wherever it lands: small towns' events, or a venue
  with no place page yet.

## 4. Page types and the indexing policy

The full rules are in `strategy.yaml` → `page_types` and `indexing_policy`. The policy rests on one principle,
already applied to the date pages (`DatePageIndexingPolicy`): **decide indexability on stable counts (the nightly
upcoming-event count), never on the page's own results of the day.** A page that flips between index and noindex
from week to week gets dropped and has to be found again.

| Tier | What | Change |
|---|---|---|
| Submit and index | upcoming events and events ended < 30 days | keep |
| | city hubs | **≥ 15 upcoming events within the radius** (today ≥ 1) |
| | date pages | ≥ 100 upcoming events (keep; review the threshold on 2026-12-21) |
| | type pages | **≥ 5 of the type** (today ≥ 1) |
| | tag pages | **whitelist + ≥ 5 upcoming** (today any tag with ≥ 1) |
| | place pages | ≥ 1 upcoming event **and a real venue name** |
| Index, do not submit | past events | keep, whatever their age (2026-09-22) |
| | place pages with history (≥ 5 events in 24 months, no upcoming one) | **new**: keeps the venue answer between two events |
| noindex, follow | empty listings, drafts, duplicates, widgets, empty members and organizers, quiet date pages | keep |
| | descriptor tags, tags under the threshold, `/recherche` | **new** |
| 410 | deleted events and places; spam once deleted | keep; delete the loan-scam page |
| Pilot, not decided | noindex past events ended > 18 months ago with 0 Google impressions in 16 months and 0 Bing clicks | only after Bing page data (§ 13), on one cohort, 8 weeks, reversible |

**Sitemap hygiene:**
- lastmod comes from a "content changed" date (name, dates, venue, description, status, offers), not `updatedAt`;
- IndexNow pings Bing on create, change, cancel and delete;
- members, organizers and search go out of the sitemap.

**Expected effect:** non-event URLs in the sitemap fall from 67,502 to under 40,000, and tags alone from 10,286 to at
most 4,185. Events stay: they are the product.

**Templates (proposals, each one shipped to a test half first):**

| Page | Today | Proposal | Data behind it |
|---|---|---|---|
| Event title | `[status] Name - Venue - By Night` | `Name[ year] à Ville[ - Venue] \| By Night`, adding only what the name lacks | 45% of event query impressions name the town; the year appears in 10% of their clicks |
| Past event | notice + old description | first screen: "terminé", then « Prochainement à \<lieu\> » and « à \<ville\> », a link to the place page | the venue-name queries in 2.5 |
| Event JSON-LD | dates without timezone (« 2026-09-09T16:00 ») | ISO dates with offset; eventStatus; offers only when real | Google's Event documentation requires a timezone for local times (not re-verified on 2026-10-28) |
| Hub title | `Agenda des sorties à X : concerts, spectacles, expos et soirées` (about 80 characters with the brand; « sortir » absent) | `Sortir à X : agenda des concerts, soirées et sorties`, on half of the hub list for 28 days | « sortir à X » is the head term in every city |
| Hub | hero, portal, 15 events | links to its date and type pages above the fold; ItemList JSON-LD; hand-written intro for the 50 busiest | competitors' listings carry markup (2.6) |
| Place title | `Sortir au <lieu>, <ville>` | `<Lieu> (<Ville>) : programme et prochains événements` + history | 2.5 |
| Tag / type title | `Danse à Toulouse` + boilerplate | `Concerts à Toulouse : agenda <mois> <année>`; Noël / Nouvel An with the year | |

## 5. Technical-first plan

This plan replaces the "refresh first" plan of the content sites: on a programmatic site the unit of work is a
template or a rule. Ordered by expected effect on the 90 days, then by effort.

| # | Change | Why | Effort | Measured by |
|---:|---|---|---|---|
| 1 | Check Google's side in the Search Console UI: Crawl stats (host status, 5xx, response time since September), Page indexing (« Crawled / Discovered – currently not indexed » per section), sitemaps | the fresh-event collapse and the autumn slide need a cause before anything else (§ 12) | 1 h | a note in this file |
| 2 | Delete the loan-scam event (410), block its author; weekly review of members' new events | site reputation | 30 min + 15 min/week | `elagage-index` |
| 3 | Sitemap lastmod from a content-change date; IndexNow on event create/change/delete | crawlers see what is new; Bing is the bigger engine | ½ day | freshness KPI, Bing crawl |
| 4 | Event title with town + year (A/B by event-id parity) | 2.1 | ½ day + 28 days | the two halves' CTR on event pages |
| 5 | Past event → venue and "what's on next" block; link to the place page | venue queries, ad page views, crawl paths to new events | ½ day | `lieux-salles` targets, pages per session |
| 6 | Seasonal readiness (from 2026-11-02): Noël / réveillon events imported and deduplicated, a « Noël à X » / « Réveillon à X » block on busy hubs from 11-15 to 12-31, Noël and Nouvel An tag titles with the year | 24% of December's event clicks | 1 day | `saisons-fetes`; December event clicks ≥ 460 |
| 7 | Hub title test (half of the hub list); hub links to date and type pages; ItemList JSON-LD on listings | 2.5, 2.6 | 1 day + 28 days | `hubs-*` positions on « sortir à X » |
| 8 | Tag whitelist; synonym tags 301 to the type page; descriptor tags noindex; `/recherche` noindex; members, organizers and search out of the sitemap | index hygiene | 1 day | non-event sitemap URLs |
| 9 | Place page: title, history, indexable with history, real-name check (addresses → merge or noindex) | 2.5 | 2 days | `lieux-salles` |
| 10 | Stale-if-error on event pages, 5xx alert at 0.2% of Bing's crawl | 2.3 | ½ day | `bing_crawl.code_5xx` |
| 11 | Hand-written intros for the 50 busiest hubs (2-3 sentences each, real places and venues) | hubs have no copy of their own | 50 × 15 min, spread out | `hubs-*` |
| 12 | Cross-source dedup rule for weekly markets and recurring venue activities (same venue + title + overlapping dates) | 2.7 | 1 day | report's cannibalization list |

**Not now:**
- a blog: no demand proof, and no capacity for a city-media cadence;
- new URL types;
- Event markup copied onto listings, which Google's guidelines exclude (ItemList only).

## 6. Pruning

- **Spam:** delete (410) and block. Review members' events weekly (the Monday report shows new pages).
- **Duplicates:** merge with a 301 to the oldest ID that carries the clicks.
- **Legacy URLs** (`/c--*`, `/x/agenda`, trailing slashes): already 301 since 2026-10-05. `elagage-index` must fall from
  4,166 impressions to under 2,000 by 12-21 and under 500 by 01-25, while `/france/…` takes over in the long tail.
- **Tags:** descriptor tags get noindex, follow and leave the sitemap. Synonyms of a type 301 to the type page.
- **Past events:** nothing is removed until the pilot is decided (§ 4).

## 7. 90-day roadmap (2026-10-29 → 2027-01-25, 4-6 h a week)

| Weeks | Dates | Work | Done when |
|---|---|---|---|
| 1 | 10-29 → 11-08 | items 1-3 of § 5 (GSC UI checks, spam, lastmod + IndexNow); export Bing's top pages by hand once (Bing Webmaster → Pages) to see whether Bing's clicks are on past events; **freeze URL scheme changes** | a note on the cause hypotheses; IndexNow live |
| 2-3 | 11-09 → 11-22 | items 4-5 (event title A/B, past-event block); item 6 starts (seasonal import check, hub block from 11-15) | both halves live; Noël block live on busy hubs |
| **Review 1** | **2026-11-23** | KPIs below; read the first 2 weeks of the title test (early: direction only); date pages indexed? | |
| 4-6 | 11-23 → 12-13 | items 7-8 (hub title test, hub links, ItemList; tag whitelist, search noindex, sitemap trims); réveillon pages checked for every busy city by 12-05 | non-event sitemap < 40,000 |
| 7-8 | 12-14 → 12-27 | item 9 (place page) and item 10; keep hands off the seasonal pages during the peak | place template live |
| **Review 2** | **2026-12-21** | KPIs; the event-title test's verdict (28 days) → roll out or revert; the date-page threshold; decide the past-event pilot with the Bing data | |
| 9-13 | 12-28 → 01-25 | roll out the winning hub title; hub intros (item 11) at ~10 a week; item 12 (dedup); measure the place pages; prepare the next version (spring: brocantes, vide-greniers, fête de la musique, summer festivals) | |
| **Review 3** | **2027-01-25** | 90-day KPIs; next version of this file | |

## 8. KPIs

28-day windows ending on the review date (seo-monitor cluster scorecard). Baseline: 2026-09-28 → 2026-10-25 from the
2026-10-28 report. The window before it (08-31 → 09-27) was summer's end and the JEP: every event cluster is down
32-38% against it, which is seasonal.

| Scope | Baseline 28 d (clicks / impr.) | 2026-11-23 | 2026-12-21 | 2027-01-25 |
|---|---|---|---|---|
| Site, Google | 1,410 / 84,679 | ≥ 1,250 clicks | ≥ 1,300 | ≥ 1,250 clicks, ≥ 85,000 impr. |
| Site, Bing | ~4,700 clicks (169/day in October) | 5xx < 1,000/day | ≥ 4,500 clicks | ≥ 4,500 clicks |
| Freshness (events < 6 months) | ~310 clicks, 8,510 pages (Oct. 1-27) | 350 | 450 | 500 |
| `ce-soir-ce-week-end` | 0 / 0 | 300 impr. | 1,000 impr., 10 clicks | 1,500 impr., 25 clicks; ≥ 4 of 6 targets on their date page |
| `saisons-fetes` | 0 / 0 | | tag pages 500 impr.; seasonal targets ≥ 1,500 impr. | December 2026 Noël + réveillon event clicks ≥ 460 |
| `hubs-metropoles` | 16 / 3,509 | 3,500 impr. | 4,000 impr.; « sortir à bordeaux » ≤ 30 | 5,000 impr., 40 clicks; Bordeaux ≤ 25, Strasbourg ≤ 20 |
| `hubs-villes-moyennes` | 20 / 3,880 | | ≥ 40 of 45 hubs shown | 5,000 impr., 45 clicks; « sortir à reims » ≤ 20, « soirée roanne » ≤ 15 |
| `marque-accueil` | 45 / 1,646 | | | ≥ 40 clicks, « by night » ≤ 6 |
| `sorties-par-type` | 0 / 77 | | 800 impr. | 1,500 impr., 15 clicks; ≥ 3 of 7 targets on their type page |
| `lieux-salles` | 0 / 31 | | template live | 1,000 impr., 15 clicks; ≥ 3 of 8 targets on a place page |
| `evenements-grandes-villes` | 589 / 30,629 | ≥ 520 | ≥ 540 | ≥ 520, ≥ 30,000 impr. |
| `evenements-longue-traine` | 669 / 40,741 | ≥ 580 | ≥ 620 | ≥ 580 |
| `elagage-index` | 71 / 4,166 | | < 2,000 impr. | < 500 impr.; spam page 410 |
| Non-event sitemap URLs | 67,502 | | < 40,000 | |

**Why "hold" is a real target.** Last year's months ran October 2,997 clicks, November 2,199 (−27%), December 2,068
and January 1,872 (−38% on October). Part of that is the 2025-2026 decline, but holding ~1,250 clicks a month into
January would beat that trajectory by about 40%. If daily Google impressions fall under 2,000 for two weeks running
(2,370 today), check indexing and crawl stats before judging the template work. Business KPIs (AdSense revenue per
1,000 sessions, `ticket-click` events in Umami) are read next to these; they are not in seo-monitor.

## 9. Measurement loop

Every Monday, `bin/seo-monitor opportunities --site=by-night.fr`, then the action mapped to each signal in
`strategy.yaml` → `measurement.signals`:

| Signal | Action |
|---|---|
| Page to push | event page: look at its queries; a venue name goes to the place page, an event name + year to the title template. Hub, date or type page: title and links, then wait 28 days |
| Weak snippet | event page on a venue or person query: fix the place page. Hub: the title test |
| Cannibalization | duplicates: merge. Venue: place page. Hub vs date/type: § 2.7 rules |
| Uncovered « sortir à / événements à X » | the city enters the intro list and the title test |
| Declining page | check the season first (Halloween, JEP, réveillon), then indexing, then 5xx |
| New pages | the URL type is intended (no widget, query string or legacy URL) |
| Sitemap URLs never shown | judge per section against the thresholds of § 4, never per URL |

Monthly, the freshness KPI. With the 16-month month × page export (`mp` table: `m` = month, `k` = URL, `c` = clicks,
`i` = impressions):

```sql
-- events created in the 6 months before each month: id above the highest id seen 6 months earlier
WITH ev AS (SELECT m, c, i, CAST(substr(k, instr(k, '--') + 2) AS INTEGER) id FROM mp WHERE k LIKE '%/soiree/%--%'),
     mx AS (SELECT m, max(id) mx FROM ev WHERE id < 4000000 GROUP BY m)
SELECT e.m, sum(e.c) AS clicks, count(*) AS pages
FROM ev e JOIN mx ON mx.m = strftime('%Y-%m', e.m || '-01', '-6 months')
WHERE e.id > mx.mx GROUP BY e.m;
```

(The `id < 4000000` guard drops the October 2026 import jump, IDs 4.0-4.27 M, from the maxima.)

On the store, the week's distinct event pages shown: `SELECT strftime('%W', date), count(DISTINCT page) FROM gsc_pages
WHERE site = 'by-night.fr' AND page LIKE '%/soiree/%' GROUP BY 1`. Bing: `bing_crawl.code_5xx` against
`crawled_pages`, every day; the monitoring task already alerts on it.

## 10. Risks

| Risk | Effect | Mitigation |
|---|---|---|
| A Google-motivated noindex cuts Bing traffic | the bigger engine loses its past-event pages | no removal before Bing page data; pilot on one cohort; reversible |
| Server 5xx again (September-October: 20% of Bing's crawl) | crawl rate cut, slower indexing of new events | stale-if-error on event pages; alert at 0.2%; Cloudflare 52x reviewed with the host |
| Too many changes at once (redesign 09-26, five URL changes in October, then this plan) | no change can be judged | URL freeze; one template change per 28 days per page type; A/B halves |
| Scaled duplicate content (source descriptions identical to OpenAgenda / tourism offices) | Google keeps 3% of the events and ranks the original | By Night's own data on every event page (venue history, nearby, all ticketing offers, hours, map); no AI-rewritten descriptions at scale |
| Spam UGC (loan scam, phone-number titles) | site reputation, manual action | delete + block, weekly review, a moderation rule for unverified members |
| Adult content next to AdSense | ad policy strikes, demonetization | decision 4 of § 13 |
| Seasonal misreading (a drop after Halloween, JEP or réveillon) | wrong fixes | check the date in the decline signal; compare to the same month last year |
| Date and type pages compete with hubs | hubs lose the queries they hold, without gain | targets measure the hand-over; 8 weeks before acting |
| Anonymised queries (89% of clicks) | target tables undercount | judge clusters on page-level clicks (scorecard), targets as direction |
| One person's time | the roadmap slips | items 1-6 are the minimum; 7-12 can slide to the next version |

## 11. What the monitoring tool cannot see

- Which pages Bing sends traffic to: seo-monitor stores totals only, so Bing's page statistics would have to be stored
  too (decision 3).
- The Google index and crawl state: Page indexing and Crawl stats exist in the UI only (§ 5 item 1, monthly).
- AdSense and Umami conversions.

## 12. Not verified / open

- **Why new events stopped ranking in April-July 2026, and why they came back on 2026-08-10.** No commit dates either
  move. Hypotheses to check in the GSC UI (Crawl stats, Page indexing) and in the deploy history:
  - the AWS → OVH + Cloudflare move (authored 2026-04-11, committed 2026-09-04);
  - a Google update;
  - crawl budget spent on the sitemap's noise.
- **Whether by-night shared the server that went down on 2026-08-05.** Its Google impressions show no dip that day;
  Cloudflare's stale cache may have hidden a short outage.
- **Google's crawl and index of the site:** crawl rate, 5xx seen by Googlebot during the Bing incident, the indexed
  count per section. None of it is in the API.
- **Bing per-page traffic.**
- **Production counts behind the proposed thresholds:** cities with ≥ 15 events within 25 km, places with ≥ 5 events
  in 24 months, tag pages with ≥ 5 events.
- **SERP on google.fr.** The search tool is not Google and showed no SERP features, and By Night appeared on none of
  the 14 queries. Whether Google shows event packs or rich results for these queries in France is unknown.
- **Google's current Event structured-data requirements** (timezone, eventStatus) were not re-read on 2026-10-28.
- **AdSense policy status** of the adult-content pages (any warnings in the AdSense account).
- **Which busy cities have their own hero copy today.**
- **Search volumes:** none were used. GSC impressions undercount where the site ranks beyond page 3, so the hub and
  type targets may be bigger than they look; they are set modestly for that reason.

## 13. Decisions for Guillaume

1. **Freeze URL scheme changes** until the 2027-01-25 review (no new URL type, no renaming). Template and policy
   changes continue.
2. **The thresholds of § 4** (hubs ≥ 15 upcoming within the radius, types ≥ 5, tag whitelist + ≥ 5, `/recherche`
   noindex, members, organizers and search out of the sitemap): accept, or ask for the production counts first.
3. **Store Bing's page statistics in seo-monitor** (the Bing Webmaster API has page and page × query statistics, GetPageStats /
   GetPageQueryStats, to confirm; the tool fetches totals today). Without it, no indexing removal can be judged on the engine that sends 3× Google's
   clicks.
4. **Adult content:** keep it as is; keep it indexable without ads on those pages; or noindex it. The data shows
   steady clicks; the risk is AdSense policy and brand.
5. **The past-event pilot** (noindex events ended > 18 months ago with no Google impression in 16 months and no Bing
   click, one cohort, 8 weeks): decide at the 2026-12-21 review, once the Bing data is there.
6. **Moderation of members' events:** a weekly review (15 min), or holding new members' events for approval.
7. **The hand-written intros of the 50 busiest hubs** (~12 h in all): worth it now, or after the title test shows
   whether hubs can move?
