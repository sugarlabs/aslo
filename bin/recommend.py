import operator


MIN_CORRELATION = 0.1


def simple(ratings, one, two):
    """Compare set one with set two."""
    a, b = ratings[one], ratings[two]
    matches = len(a.intersection(b))
    return (2 * matches) / float(len(a) + len(b))


def top_matches(ratings, this, n=10, compare=simple):
    """Get the top n matching add-ons for this."""
    scores = [(compare(ratings, this, other), other)
              for other in ratings if this != other]
    return sorted(scores, reverse=True)[:n]


def rank_all(ratings_dict, compare=simple):
    """
    Compare each add-on with all other add-ons, yielding

       (addon, {other: score, ...})

    """
    addons = ratings_dict.keys()
    for addon in addons:
        scores = {}
        for other in addons:
            if other != addon:
                scores[other] = compare(ratings_dict, addon, other)
        yield addon, scores


def top(rankings, n=20):
    """
    Sort each (addon, {other: score, ...}) dict, yielding

        (addon, [(other, score), ...])

    """
    for addon, scores in rankings:
        ranked = sorted(scores.iteritems(), key=operator.itemgetter(1),
                        reverse=True)
        yield addon, filter(lambda x: x[1] > MIN_CORRELATION, ranked[:n])
