import { useMutation, useQueryClient } from '@tanstack/react-query';

import { updateProfile } from '@/api/profile';
import { UpdateProfileRequest } from '@/types/profile';

import { profileQueryKeys } from './queryKeys';

export function useUpdateProfile() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: UpdateProfileRequest) =>
      updateProfile(payload).then((response) => response.data),
    onSuccess: (data) => {
      queryClient.setQueryData(profileQueryKeys.profile, data);
    },
  });
}
