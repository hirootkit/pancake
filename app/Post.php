<?php

namespace App;

use App\Services\Markdowner;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    protected $dates = ['published_at'];
    protected $fillable = [
        'title', 'subtitle', 'content_raw', 'page_image', 'meta_description',
        'layout', 'is_draft', 'published_at'
    ];

    /**
     * The many-to-many relationship between posts and tags.
     */
    public function tags()
    {
        return $this->belongsToMany('App\Tag', 'post_tag_pivot');
    }

    /**
     * @param $value
     */
    public function setTitleAttribute($value)
    {
        $this->attributes['title'] = $value;

        if (!$this->exists) {
            $this->setUniqueSlug($value, '');
        }
    }

    /**
     * Recursive routine to set a unique slug
     *
     * @param $title
     * @param $extra
     */
    protected function setUniqueSlug($title, $extra)
    {
        $slug = str_slug($title . '-' . $extra);

        if (static::whereSlug($slug)->exists()) {
            $this->setUniqueSlug($title, $extra + 1);
            return;
        }

        $this->attributes['slug'] = $slug;
    }

    /**
     * Set the HTML content automatically when the raw content is set
     *
     * @param $value
     */
    public function setContentRawAttribute($value)
    {
        $markdown = new Markdowner();

        $this->attributes['content_raw'] = $value;
        $this->attributes['content_html'] = $markdown->toHTML($value);
    }

    /**
     * Sync tag relation adding new tags as needed
     *
     * @param array $tags
     */
    public function syncTags(array $tags)
    {
        Tag::addNeededTags($tags);

        if (count($tags)) {
            $this->tags()->sync(
                Tag::whereIn('tag', $tags)->lists('id')->all()
            );
            return;
        }

        $this->tags()->detach();
    }

    /**
     * Return the date portion of published_at
     *
     * @param $value
     * @return mixed
     */
    public function getPublishDateAttribute($value)
    {
        return $this->published_at->format('M-j-Y');
    }

    /**
     * Return the time portion of published_at
     *
     * @param $value
     * @return mixed
     */
    public function getPublishTimeAttribute($value)
    {
        return $this->published_at->format('g:i A');
    }

    /**
     * Alias for content_raw
     *
     * @param $value
     * @return mixed
     */
    public function getContentAttribute($value)
    {
        return $this->content_raw;
    }

    /**
     * Return URL to post
     *
     * @param Tag|null $tag
     * @return string
     */
    public function url(Tag $tag = null)
    {
        $url = url('blog/' . $this->slug);
        if ($tag) {
            $url .= '?tag=' . urlencode($tag->tag);
        }

        return $url;
    }

    /**
     * Return array of tag links
     *
     * @param string $entity
     * @param string $base
     * @return array
     */
    public function tagLinks($entity, $base = '/pancake/blog?tag=%TAG%')
    {
        $tags = $this->tags()->lists('tag');
        $return = [];

        foreach ($tags as $tag) {
            $url = str_replace('%TAG%', urlencode($tag), $base);

            if ($entity == 'slider') {
                $return[] = e($tag);
            } elseif ($entity == 'post') {
                $return[] = '<a class="tag" href="' . $url . '">' . e($tag) . '</a>';
            } else {
                $return[] = '<a class="tag-name" href="' . $url . '">' . e($tag) . '</a>';
            }
        }
        
        return $return;
    }

    /**
     * Return next post after this one or null
     *
     * @param Tag|null $tag
     * @return mixed
     */
    public function newerPost(Tag $tag = null)
    {
        $query = static::where('published_at', '>', $this->published_at)
            ->where('published_at', '<=', Carbon::now())
            ->where('is_draft', 0)
            ->orderBy('published_at', 'asc');

        if ($tag) {
            $query = $query->whereHas('tags', function ($q) use ($tag) {
                $q->where('tag', '=', $tag->tag);
            });
        }

        return $query->first();
    }

    /**
     * Return older post after this one or null
     *
     * @param Tag|null $tag
     * @return mixed
     */
    public function olderPost(Tag $tag = null)
    {
        $query = static::where('published_at', '<', $this->published_at)
            ->where('is_draft', 0)
            ->orderBy('published_at', 'desc');

        if ($tag) {
            $query = $query->whereHas('tags', function ($q) use ($tag) {
                $q->where('tag', '=', $tag->tag);
            });
        }

        return $query->first();
    }
}
