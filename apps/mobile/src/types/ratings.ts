/**
 * Mirrors RendersRatingResponses::toResponse() exactly
 * (apps/web/app/Http/Controllers/Concerns) — never exposes rater/ratee
 * ids, only the rating's own content, since identity is already implied
 * by which slot ("mine" vs "counterpart") it appears under.
 */

export interface Rating {
  id: string;
  transfer_id: string;
  score: number;
  comment: string | null;
  submitted_at: string;
}

export interface SubmitRatingRequest {
  score: number;
  comment?: string;
}

/**
 * Double-blind by construction (ADR-024 §5): `counterpart` is null both
 * when the counterpart hasn't rated yet AND when they have but the
 * reveal hasn't happened yet — `counterpart_submitted` is the only way
 * to distinguish those two cases client-side.
 */
export interface TransferRatings {
  mine: Rating | null;
  counterpart: Rating | null;
  counterpart_submitted: boolean;
}
