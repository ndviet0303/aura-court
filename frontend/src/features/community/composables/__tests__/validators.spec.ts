import { describe, expect, it } from 'vitest'
import { POST_MAX } from '../../types/community'
import { imageProblem } from '../useImageUploads'
import { postProblem } from '../usePostComposer'

describe('postProblem', () => {
  const share = { type: 'share' as const, content: 'Hello', images: [] }
  const match = {
    ...share,
    type: 'matchmaking' as const,
    sport: 'badminton' as const,
    play_at: '2026-10-25T18:00:00+07:00',
    venue_id: 3,
  }

  it('needs content within the limit', () => {
    expect(postProblem(share)).toBeNull()
    expect(postProblem({ ...share, content: '   ' })).toBe('community.composer.contentRequired')
    expect(postProblem({ ...share, content: 'x'.repeat(POST_MAX + 1) })).toBe(
      'community.composer.contentTooLong',
    )
  })

  it('needs sport, play time and a venue or address for matchmaking', () => {
    expect(postProblem(match)).toBeNull()
    expect(postProblem({ ...match, sport: null })).toBe('community.composer.sportRequired')
    expect(postProblem({ ...match, play_at: null })).toBe('community.composer.playAtRequired')
    expect(postProblem({ ...match, venue_id: null, address: ' ' })).toBe(
      'community.composer.placeRequired',
    )
    expect(postProblem({ ...match, venue_id: null, address: '12 Lê Lợi' })).toBeNull()
  })
})

describe('imageProblem', () => {
  it('accepts jpg, png and webp up to 5 MB', () => {
    expect(imageProblem({ type: 'image/webp', size: 5 * 1024 * 1024 })).toBeNull()
    expect(imageProblem({ type: 'image/gif', size: 10 })).toBe('community.images.badType')
    expect(imageProblem({ type: 'image/png', size: 5 * 1024 * 1024 + 1 })).toBe(
      'community.images.tooLarge',
    )
  })
})
